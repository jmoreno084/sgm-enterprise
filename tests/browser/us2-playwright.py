"""Run against the disposable -KeepAcceptance environment; default TLS only."""
import base64
import json
import traceback
import uuid
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
META = json.loads((ROOT / 'storage/acceptance/local.json').read_text(encoding='utf-8-sig'))
assert META['url'] == 'https://localhost:18443'
assert META['web_container'].startswith('sgpm-foundation-')
OUT = ROOT / 'ops/evidence/us2-browser'
OUT.mkdir(parents=True, exist_ok=True)
RESULTS = []
ERRORS = []

def check(label, condition):
    RESULTS.append({'check': label, 'pass': bool(condition)})
    print(('PASS ' if condition else 'FAIL ') + label, flush=True)

def login(page, email):
    page.goto(META['url'] + '/login', wait_until='networkidle')
    page.locator('#correo').fill(email)
    page.locator('#password').fill(META['fixture_password'])
    page.get_by_role('button', name='Ingresar', exact=True).click()
    page.wait_for_url(lambda url: '/login' not in url)
    page.wait_for_load_state('networkidle')

def submit(page, form, method, path):
    with page.expect_response(lambda r: path in r.url and r.request.method == method) as response:
        page.locator(form + ' button[type=submit]').click()
    page.wait_for_load_state('networkidle')
    expect(page.locator(form + ' button[type=submit]')).to_be_enabled()
    return response.value

def capture(page, name):
    page.screenshot(path=str(OUT / (name + '.png')), full_page=True)

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={'width': 1440, 'height': 1000})
        page = context.new_page()
        page.on('pageerror', lambda error: ERRORS.append(str(error)))
        login(page, META['fixture_email'])
        page.get_by_role('link', name='Empresas y proyectos', exact=True).click()
        page.wait_for_load_state('networkidle')
        expect(page.locator('#company-editor')).to_be_visible()
        check('Admin company editor visible', True)
        name = 'Minería José Muñoz ' + uuid.uuid4().hex[:8]
        nit = 'UI-' + uuid.uuid4().hex[:10]
        def fill_company():
            for key, value in {'nit':nit, 'razon_social':name, 'correo':'ui@example.invalid', 'ciudad':'Bogotá', 'departamento':'Cundinamarca'}.items():
                page.locator('#company-' + key).fill(value)
        fill_company()
        created = submit(page, '#company-form', 'POST', '/api/v1/empresas')
        check('Create company in UI', created.status == 201)
        company = created.json()['data']
        page.locator('#company-filter').fill(name)
        submit(page, '#company-search', 'GET', '/api/v1/empresas?')
        expect(page.locator('#company-list tr')).to_have_count(1)
        check('Company search UTF-8', name in page.locator('#company-list').inner_text())
        page.locator('#company-form button[type=reset]').click()
        fill_company()
        duplicate = submit(page, '#company-form', 'POST', '/api/v1/empresas')
        expect(page.locator('#module-status')).to_be_focused()
        check('Company duplicate friendly 409', duplicate.status == 409)
        capture(page, 'company-duplicate')
        page.get_by_role('button', name='Editar', exact=True).click()
        page.locator('#company-logo').set_input_files({'name':'logo.png','mimeType':'image/png','buffer':base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS0AAAAASUVORK5CYII=')})
        logo = submit(page, '#logo-form', 'POST', '/logo')
        check('Logo uploaded through UI', logo.status == 200 and page.get_by_role('link', name='Ver logo').count() == 1)
        for width in [360,768,1440]:
            page.set_viewport_size({'width':width,'height':1000})
            check('Company document fits ' + str(width), page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            page.locator('#company-nit').focus()
            page.keyboard.press('Tab')
            expect(page.locator('#company-razon_social')).to_be_focused()
            check('Company keyboard focus ' + str(width), True)
            capture(page, 'companies-' + str(width))
        page.get_by_role('link', name='Proyectos', exact=True).click()
        page.wait_for_load_state('networkidle')
        expect(page.locator('#project-editor')).to_be_visible()
        page.locator('#project-nombre').fill('Proyecto Andino Ñ')
        page.locator('#project-mineral_principal').fill('Oro')
        page.locator('#project-fecha_inicio').fill('2026-10-10')
        page.locator('#project-fecha_fin').fill('2026-10-09')
        invalid = submit(page, '#project-form', 'POST', '/proyectos')
        check('Invalid date range 400', invalid.status == 400)
        page.locator('#project-fecha_fin').fill('2026-10-20')
        project_response = submit(page, '#project-form', 'POST', '/proyectos')
        check('Create project through UI', project_response.status == 201)
        project = project_response.json()['data']
        expect(page.locator('#project-list')).to_contain_text('Proyecto Andino Ñ')
        for width in [360,768,1440]:
            page.set_viewport_size({'width':width,'height':1000})
            check('Project document fits ' + str(width), page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            capture(page, 'projects-' + str(width))
        page.get_by_role('link', name='Expedientes', exact=True).click()
        page.wait_for_load_state('networkidle')
        expect(page.locator('#file-editor')).to_be_visible()
        page.locator('#file-code').fill('NO-EXISTE')
        invalid = submit(page, '#file-form', 'POST', '/expedientes')
        check('Unknown source 400 in UI', invalid.status == 400)
        page.locator('#file-code').fill('T-A')
        associated = submit(page, '#file-form', 'POST', '/expedientes')
        check('Associate existing source in UI', associated.status == 201)
        page.locator('#file-code').fill('T-A')
        repeated = submit(page, '#file-form', 'POST', '/expedientes')
        expect(page.locator('#module-status')).to_be_focused()
        check('Duplicate source 409 and one row', repeated.status == 409 and page.locator('#file-list tr').count() == 1)
        capture(page, 'file-duplicate')
        expect(page.locator('#centroid-status')).to_contain_text('calculado en el servidor')
        for width in [360,768,1440]:
            page.set_viewport_size({'width':width,'height':1000})
            check('Files document fits ' + str(width), page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
            capture(page, 'files-' + str(width))
        page.once('dialog', lambda dialog: dialog.accept())
        with page.expect_response(lambda r: r.request.method == 'DELETE' and '/expedientes/' in r.url) as removed:
            page.get_by_role('button', name='Desvincular', exact=True).click()
        page.wait_for_load_state('networkidle')
        expect(page.locator('#file-list tr')).to_have_count(0)
        check('Unlink source and show empty state', removed.value.status == 204 and 'No hay registros' in page.locator('#module-status').inner_text())
        capture(page, 'files-empty')
        for role in ['gerente','geologo']:
            other_context = browser.new_context(viewport={'width':360,'height':900})
            other = other_context.new_page()
            other.on('pageerror', lambda error: ERRORS.append(str(error)))
            login(other, role + '@example.invalid')
            other.goto(META['url'] + '/empresas', wait_until='networkidle')
            expect(other.locator('#company-editor')).to_be_hidden()
            check(role + ' cannot see company editor', other.locator('#company-list tr').count() == 1)
            other.get_by_role('link', name='Proyectos', exact=True).click()
            other.wait_for_load_state('networkidle')
            check(role + ' project edit controls follow role', other.locator('#project-editor').is_visible() == (role == 'geologo'))
            capture(other, role + '-projects')
            check(role + ' cannot access unrelated project', other.goto(META['url'] + '/proyectos/' + str(project['id'])).status == 404)
            other_context.close()
        check('No uncaught JavaScript errors', not ERRORS)
        browser.close()

try:
    run()
except Exception as error:
    check('Scenario finishes without interruption', False)
    print(type(error).__name__, [f.lineno for f in traceback.extract_tb(error.__traceback__) if f.filename == __file__], flush=True)
finally:
    (OUT / 'results.json').write_text(json.dumps(RESULTS,ensure_ascii=False,indent=2),encoding='utf-8')
raise SystemExit(1 if any(not row['pass'] for row in RESULTS) else 0)
