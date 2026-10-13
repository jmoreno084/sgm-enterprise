"""US1 acceptance on the isolated Windows HTTPS fixture; never bypass TLS.

Run: python tests/browser/us1-playwright.py
Requires ops/docker/test-foundation.ps1 -KeepAcceptance and user-managed trust.
Only the labelled temporary fixture is used. No email is dispatched.
"""
import json
import subprocess
import uuid
import hashlib
import traceback
from importlib.metadata import version
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'ops/evidence/us1-browser'
OUT.mkdir(parents=True, exist_ok=True)
META = json.loads((ROOT / 'storage/acceptance/local.json').read_text(encoding='utf-8-sig'))
BASE = 'https://localhost:18443'
assert META['url'] == BASE
for key in ['web_container', 'database_container']:
    name = META[key]
    assert name.startswith('sgpm-foundation-')
    label = subprocess.check_output(['docker', 'inspect', '--format', '{{index .Config.Labels "sgpm.acceptance"}}', name], text=True).strip()
    assert label == 'true', 'Only labelled acceptance containers are allowed'
RESULTS = []
ERRORS = []

def check(name, condition, detail=''):
    RESULTS.append({'check': name, 'pass': bool(condition), 'detail': detail})
    print(('PASS ' if condition else 'FAIL ') + name, flush=True)

def shot(page, name):
    page.screenshot(path=str(OUT / (name + '.png')), full_page=True)

def watch(page):
    page.on('pageerror', lambda error: ERRORS.append(str(error)))

def api(page, path):
    return page.evaluate('async path => {const r=await fetch(path); return {status:r.status, body:await r.json()};}', path)

def login(page, email, password):
    page.goto(BASE + '/login', wait_until='networkidle')
    page.locator('#correo').fill(email)
    page.locator('#password').fill(password)
    page.locator('button[type=submit]').click()
    page.wait_for_url(lambda url: '/login' not in url, timeout=15000)
    page.wait_for_load_state('networkidle')

def search(page, email):
    page.locator('#search-correo').fill(email)
    with page.expect_response(lambda r: '/api/v1/usuarios?' in r.url):
        page.locator('#search-users button').click()
    expect(page.locator('#users-status')).not_to_have_text('Cargando usuarios…')

def save_user(page, method):
    with page.expect_response(lambda r: '/api/v1/usuarios' in r.url and r.request.method == method) as response:
        page.locator('#user-form button[type=submit]').click()
    page.wait_for_load_state('networkidle')
    expect(page.locator('#user-password')).to_have_value('')
    return response.value

def member_save(page, method):
    with page.expect_response(lambda r: '/usuarios' in r.url and '/empresas/' in r.url and r.request.method == method) as response:
        page.locator('#membership-form button[type=submit]').click()
    page.wait_for_load_state('networkidle')
    return response.value

def recovery(page, email):
    page.locator('#correo').fill(email)
    with page.expect_response(lambda r: '/api/v1/recuperaciones' in r.url and r.request.method == 'POST') as response:
        page.locator('button[type=submit]').click()
    expect(page.locator('button[type=submit]')).to_be_enabled()
    return response.value.status, page.locator('#auth-status').inner_text()

def run():
    # Each run owns a fresh IP rate window in this disposable database only.
    previous_ip = subprocess.check_output(['docker', 'exec', META['database_container'], 'psql', '-U', 'postgres', '-d', 'sgpm_acceptance', '-At', '-c', "SELECT ip FROM sgpm.auditoria WHERE accion='LOGIN' ORDER BY id DESC LIMIT 1"], text=True).strip()
    keys = [hashlib.sha256((op + ':ip:' + ip).encode()).hexdigest()
            for op in ['login', 'recovery'] for ip in ['127.0.0.1', '::1', previous_ip]]
    sql = "DELETE FROM sgpm.limites_acceso WHERE clave_hash IN (" + ','.join("'" + key + "'" for key in keys) + ')'
    subprocess.run(['docker', 'exec', META['database_container'], 'psql', '-U', 'postgres', '-d', 'sgpm_acceptance', '-v', 'ON_ERROR_STOP=1', '-c', sql], check=True, capture_output=True)
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        (OUT / 'environment.json').write_text(json.dumps({
            'playwright': version('playwright'), 'chromium': browser.version,
            'base_url': BASE, 'tls_verification': 'default, enabled',
            'rate_window_setup': 'only IP counters in labelled disposable acceptance database',
        }, indent=2), encoding='utf-8')
        context = browser.new_context(viewport={'width': 1440, 'height': 1000})
        page = context.new_page()
        watch(page)
        response = page.goto(BASE + '/login', wait_until='networkidle')
        check('HTTPS trusted with default certificate validation', response.status == 200)
        for width in [360, 768, 1440]:
            page.set_viewport_size({'width': width, 'height': 900})
            page.reload(wait_until='networkidle')
            check('Login labels ' + str(width), page.get_by_label('Correo', exact=True).count() == 1 and page.get_by_label('Clave', exact=True).count() == 1)
            check('Login no horizontal overflow ' + str(width), page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            order = []
            for _ in range(5):
                page.keyboard.press('Tab')
                order.append(page.evaluate('document.activeElement.id || document.activeElement.textContent.trim()'))
            check('Login Tab order ' + str(width), order == ['Saltar al contenido', 'correo', 'password', 'Ingresar', 'Recuperar acceso'], str(order))
            shot(page, 'login-' + str(width) + '-keyboard')
        page.get_by_role('link', name='Recuperar acceso', exact=True).click()
        page.wait_for_load_state('networkidle')
        check('Recovery link accessible by keyboard', page.url == BASE + '/recuperar')
        for width in [360, 768, 1440]:
            page.set_viewport_size({'width': width, 'height': 900})
            page.reload(wait_until='networkidle')
            order = []
            for _ in range(4):
                page.keyboard.press('Tab')
                order.append(page.evaluate('document.activeElement.id || document.activeElement.textContent.trim()'))
            check('Recovery Tab order ' + str(width), order == ['Saltar al contenido', 'correo', 'Continuar', 'Ingresar'], str(order))
            check('Recovery no horizontal overflow ' + str(width), page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            shot(page, 'recovery-' + str(width) + '-keyboard')
        page.goto(BASE + '/login', wait_until='networkidle')
        page.locator('#correo').fill(META['fixture_email'])
        page.locator('#password').fill('incorrecta-playwright')
        with page.expect_response(lambda r: r.request.method == 'POST' and '/api/v1/sesion' in r.url) as failed:
            page.locator('button[type=submit]').click()
        expect(page.locator('#password')).to_have_value('')
        expect(page.locator('#auth-status')).to_be_focused()
        message = page.locator('#auth-status').inner_text()
        check('Wrong password: 401, friendly focused error and cleared password', failed.value.status == 401 and bool(message) and all(x not in message for x in ['SQLSTATE', 'Stack trace', 'Fatal error']))
        shot(page, 'login-invalid')
        page.set_viewport_size({'width': 1440, 'height': 1000})
        login(page, META['fixture_email'], META['fixture_password'])
        expect(page.locator('#page-status')).to_contain_text('Página 1')
        check('Admin users and companies loaded', page.locator('#users-list tr').count() > 0 and page.locator('#member-company option').count() >= 2)
        shot(page, 'users-desktop')
        total = api(page, '/api/v1/usuarios')['body']['data']['total']
        if total <= 25:
            # Prepare enough disposable rows through the real authorized API.
            page.evaluate('''async count => {
                const {request} = await import('/assets/js/modules/http.js');
                for(let i=0;i<count;i++) await request('/api/v1/usuarios', {method:'POST', body:{
                    correo:'pagination-'+crypto.randomUUID()+'@example.invalid',
                    nombre:'Paginación de prueba', numero_documento:'PÁGINA-'+i,
                    password:crypto.randomUUID(), estado:'ACTIVO', es_administrador:false
                }});
            }''', 26 - total)
            page.reload(wait_until='networkidle')
            total = api(page, '/api/v1/usuarios')['body']['data']['total']
        if total > 25:
            page.locator('#next-page').click()
            expect(page.locator('#page-status')).to_contain_text('Página 2')
            page.locator('#previous-page').click()
            expect(page.locator('#page-status')).to_contain_text('Página 1')
            check('Pagination next and previous', True)
        else:
            check('Pagination initial boundaries', page.locator('#previous-page').is_disabled() and page.locator('#next-page').is_disabled(), 'Fixture has ' + str(total) + ' users')
        email = 'visual-' + uuid.uuid4().hex[:10] + '@example.invalid'
        password = uuid.uuid4().hex
        page.locator('#user-correo').fill(email)
        page.locator('#user-nombre').fill('José Muñoz — Bogotá')
        page.locator('#user-numero_documento').fill('CÉDULA-Ñ-123')
        page.locator('#user-password').fill(password)
        created = save_user(page, 'POST')
        check('Create test user', created.status == 201)
        search(page, email)
        row = page.locator('#users-list tr').filter(has_text=email)
        expect(row).to_have_count(1)
        row.get_by_role('button', name='Editar', exact=True).click()
        expect(page.locator('#user-nombre')).to_have_value('José Muñoz — Bogotá')
        expect(page.locator('#user-numero_documento')).to_have_value('CÉDULA-Ñ-123')
        uid = int(page.locator('#user-form input[name=id]').input_value())
        check('Accented name and document round trip', True)
        page.locator('#new-user').click()
        page.locator('#user-correo').fill('  ' + email.upper() + '  ')
        page.locator('#user-nombre').fill('Duplicado')
        page.locator('#user-numero_documento').fill('DUPLICADO')
        page.locator('#user-password').fill(password)
        duplicate = save_user(page, 'POST')
        expect(page.locator('#users-status')).to_be_focused()
        check('Normalized duplicate email returns friendly 409 without duplicate', duplicate.status == 409 and api(page, '/api/v1/usuarios?correo=' + email)['body']['data']['total'] == 1)
        shot(page, 'user-duplicate')
        row.get_by_role('button', name='Editar', exact=True).click()
        second = context.new_page()
        watch(second)
        second.goto(BASE + '/usuarios', wait_until='networkidle')
        search(second, email)
        second.locator('#users-list tr').filter(has_text=email).get_by_role('button', name='Editar', exact=True).click()
        page.locator('#user-nombre').fill('José Muñoz — Primera edición')
        check('First concurrent edit saved', save_user(page, 'PUT').status == 200)
        second.locator('#user-nombre').fill('Edición obsoleta')
        stale = save_user(second, 'PUT')
        check('Stale edit 409 preserves first edit', stale.status == 409 and api(page, '/api/v1/usuarios/' + str(uid))['body']['data']['nombre'] == 'José Muñoz — Primera edición')
        shot(second, 'user-concurrency')
        second.close()
        options = page.locator('#member-company option').evaluate_all('(options)=>options.map(o=>({id:o.value,text:o.textContent}))')
        company_a = next(x for x in options if 'Empresa A' in x['text'])
        company_b = next(x for x in options if 'Empresa B' in x['text'])
        page.locator('#member-company').select_option(company_a['id'])
        page.locator('#member-user').fill(str(uid))
        page.locator('#member-role').select_option('GEOLOGO')
        check('Assign GEOLOGO in Empresa A', member_save(page, 'POST').status == 201)
        item = page.locator('#memberships-list li').filter(has_text='José Muñoz')
        expect(item).to_contain_text('GEOLOGO')
        member_duplicate = member_save(page, 'POST')
        check('Duplicate membership 409', member_duplicate.status == 409)
        item.get_by_role('button', name='Editar', exact=True).click()
        page.locator('#member-role').select_option('INVERSIONISTA')
        check('Edit membership role', member_save(page, 'PUT').status == 200)
        item.get_by_role('button', name='Editar', exact=True).click()
        page.locator('#member-role').select_option('GEOLOGO')
        check('Restore GEOLOGO role', member_save(page, 'PUT').status == 200)
        geo_context = browser.new_context(viewport={'width': 360, 'height': 900})
        geo = geo_context.new_page()
        watch(geo)
        login(geo, email, password)
        companies = api(geo, '/api/v1/sesion')['body']['data']['empresas']
        check('Geologist authorized only in A, no B inheritance', {str(x['id']) for x in companies} == {company_a['id']})
        check('Geologist users API forbidden', api(geo, '/api/v1/usuarios')['status'] == 403)
        check('Geologist memberships API forbidden', api(geo, '/api/v1/empresas/' + company_a['id'] + '/usuarios')['status'] == 403)
        check('Geologist users page forbidden', geo.goto(BASE + '/usuarios').status == 403)
        geo.goto(BASE + '/', wait_until='networkidle')
        shot(geo, 'geologist-mobile')
        page.once('dialog', lambda dialog: dialog.accept())
        with page.expect_response(lambda r: r.request.method == 'DELETE' and '/empresas/' in r.url) as revoked:
            item.get_by_role('button', name='Revocar', exact=True).click()
        page.wait_for_load_state('networkidle')
        check('Revocation removes A on next authenticated request', revoked.value.status == 204 and api(geo, '/api/v1/sesion')['body']['data']['empresas'] == [])
        geo.reload(wait_until='networkidle')
        expect(geo.locator('#session-status')).to_contain_text('No tienes empresas activas')
        geo.locator('#logout').click()
        geo.wait_for_url(BASE + '/login')
        check('Logout requires authentication on users', geo.goto(BASE + '/usuarios').status == 401)
        for width in [360, 768, 1440]:
            page.set_viewport_size({'width': width, 'height': 1000})
            check('Users no horizontal overflow ' + str(width), page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            page.locator('#user-correo').focus()
            page.keyboard.press('Tab')
            check('Users keyboard focus ' + str(width), page.locator('#user-nombre').evaluate('(e)=>e===document.activeElement'))
            shot(page, 'users-' + str(width))
        # Walk every enabled user/membership control in its native Tab order.
        page.reload(wait_until='networkidle')
        controls = page.locator('a[href],button,input:not([type=hidden]),select').evaluate_all('''items => items.filter(e => !e.disabled && e.getClientRects().length).map(e=>({tag:e.tagName,id:e.id,text:e.textContent.trim()}))''')
        reached = []
        for _ in controls:
            page.keyboard.press('Tab')
            reached.append(page.evaluate('({tag:document.activeElement.tagName,id:document.activeElement.id,text:document.activeElement.textContent.trim()})'))
        check('All enabled users and membership controls reachable with Tab', reached == controls, str(len(controls)) + ' controls')
        search(page, 'not-present-' + uuid.uuid4().hex + '@example.invalid')
        expect(page.locator('#users-status')).to_have_text('No hay usuarios con estos filtros.')
        check('Filtered empty state', page.locator('#users-list tr').count() == 0)
        shot(page, 'users-empty')
        recovery_context = browser.new_context(viewport={'width': 360, 'height': 900})
        rec = recovery_context.new_page()
        watch(rec)
        rec.goto(BASE + '/recuperar', wait_until='networkidle')
        known_status, known_message = recovery(rec, email)
        unknown_status, unknown_message = recovery(rec, 'unknown-' + uuid.uuid4().hex + '@example.invalid')
        check('Recovery known/unknown identical 202 message', known_status == unknown_status == 202 and known_message == unknown_message, str([known_status, unknown_status]))
        check('Recovery does not render secrets', rec.locator('input[type=password]:visible').count() == 0 and 'token' not in rec.locator('body').inner_text().lower())
        shot(rec, 'recovery-uniform')
        # Read only the encrypted fixture outbox via the temporary PHP container.
        # Capture token in memory; never write it to evidence, commands, or logs.
        php = r'''require '/workspace/src/bootstrap.php';
        $c=new Sgpm\Infrastructure\Configuration(require '/workspace/config/config.php');
        $c->assertTestDatabase();$d=new Sgpm\Infrastructure\Database($c);
        $r=$d->execute("SELECT payload_cifrado FROM sgpm.notificaciones_outbox WHERE tipo='RECUPERACION' AND usuario_destino_id=:id ORDER BY id DESC LIMIT 1",array('id'=>''' + str(uid) + r'''))->fetch();
        $v=(new Sgpm\Infrastructure\PayloadCipher($c))->decrypt($r['payload_cifrado']);echo $v['token'];'''
        token = subprocess.check_output(['docker', 'exec', META['web_container'], 'php', '-r', php], text=True).strip()
        assert len(token) == 64
        rec.goto('about:blank')
        rec.goto(BASE + '/recuperar#token=' + token, wait_until='networkidle')
        check('Recovery fragment removed from address bar', rec.url == BASE + '/recuperar')
        new_password = uuid.uuid4().hex
        rec.locator('#password').fill(new_password)
        rec.locator('#confirmacion').fill('diferente-clave-valida')
        with rec.expect_response(lambda r: '/recuperaciones/confirmar' in r.url) as mismatch:
            rec.locator('button[type=submit]').click()
        expect(rec.locator('#password')).to_have_value('')
        expect(rec.locator('#confirmacion')).to_have_value('')
        expect(rec.locator('#auth-status')).to_be_focused()
        check('Recovery error clears both keys and focuses message', mismatch.value.status == 400)
        shot(rec, 'recovery-mismatch')
        rec.locator('#password').fill(new_password)
        rec.locator('#confirmacion').fill(new_password)
        with rec.expect_response(lambda r: '/recuperaciones/confirmar' in r.url) as confirmed:
            rec.locator('button[type=submit]').click()
        rec.wait_for_url(BASE + '/login')
        expect(rec.locator('#password')).to_have_value('')
        check('Recovery success redirects with empty key', confirmed.value.status == 200)
        login(rec, email, new_password)
        check('New password works', rec.url == BASE + '/')
        rec.goto(BASE + '/recuperar#token=' + token, wait_until='networkidle')
        rec.locator('#password').fill(uuid.uuid4().hex)
        rec.locator('#confirmacion').fill(rec.locator('#password').input_value())
        with rec.expect_response(lambda r: '/recuperaciones/confirmar' in r.url) as reused:
            rec.locator('button[type=submit]').click()
        expect(rec.locator('#password')).to_have_value('')
        expect(rec.locator('#confirmacion')).to_have_value('')
        check('Recovery token single use', reused.value.status == 400)
        shot(rec, 'recovery-reused')
        rec.goto(BASE + '/recuperar', wait_until='networkidle')
        statuses = [recovery(rec, email)[0] for _ in range(2)]
        check('Recovery three per IP window then 429', statuses == [202, 429], str(statuses))
        shot(rec, 'recovery-rate-limit')
        check('No uncaught JavaScript errors', not ERRORS, '; '.join(ERRORS))
        browser.close()

try:
    run()
except Exception as error:
    # Playwright errors can contain URLs with tokens. Persist only the class.
    check('Scenario completed without interruption', False, type(error).__name__)
    print('Interrupted:', type(error).__name__, flush=True)
    print('Runner line:', [frame.lineno for frame in traceback.extract_tb(error.__traceback__) if frame.filename == __file__], flush=True)
finally:
    (OUT / 'results.json').write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2), encoding='utf-8')
raise SystemExit(1 if any(not item['pass'] for item in RESULTS) else 0)
