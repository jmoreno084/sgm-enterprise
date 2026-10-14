"""Disposable HTTPS acceptance. Uses OS trust; never bypasses certificate validation."""
import json
import subprocess
import traceback
import uuid
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
META = json.loads((ROOT / 'storage/acceptance/local.json').read_text(encoding='utf-8-sig'))
assert META['url'] == 'https://localhost:18443'
assert META['web_container'].startswith('sgpm-foundation-')
OUT = ROOT / 'ops/evidence/integral-browser'
OUT.mkdir(parents=True, exist_ok=True)
CHECKS, ERRORS = [], []

def check(label, value):
    CHECKS.append({'check': label, 'pass': bool(value)})
    print(('PASS ' if value else 'FAIL ') + label, flush=True)

def login(page, role):
    page.goto(META['url'] + '/login', wait_until='networkidle')
    page.locator('#correo').fill(role + '@example.invalid')
    page.locator('#password').fill(META['fixture_password'])
    with page.expect_response(lambda r: '/api/v1/sesion' in r.url and r.request.method == 'POST') as response:
        page.get_by_role('button', name='Ingresar', exact=True).click()
    if response.value.status != 200:
        raise RuntimeError('Login fixture HTTP ' + str(response.value.status))
    page.wait_for_url(lambda url: '/login' not in url)

def api(page, method, path, data=None, status=200):
    result = page.evaluate("""async ({method, path, data}) => {
        const session = await (await fetch('/api/v1/sesion')).json();
        const response = await fetch('/api/v1' + path, {method,
            headers: {'X-CSRF-Token': session.data.csrf, 'Content-Type': 'application/json'},
            body: data === null ? undefined : JSON.stringify(data)});
        return {status: response.status, body: response.status === 204 ? null : await response.json()};
    }""", {'method': method, 'path': path, 'data': data})
    if result['status'] != status:
        raise RuntimeError(f'{method} {path}: {result["status"]}, expected {status}')
    return result['body']['data'] if status != 204 else None

def responsive(page, name):
    for width in [360, 768, 1440]:
        page.set_viewport_size({'width': width, 'height': 1000})
        check(f'{name} fits {width}', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
        page.locator('nav a').first.focus()
        page.keyboard.press('Tab')
        check(f'{name} keyboard {width}', page.evaluate('document.activeElement !== document.body'))
        page.screenshot(path=str(OUT / f'{name}-{width}.png'), full_page=True)

def submit(page, form, method, fragment):
    with page.expect_response(lambda r: r.request.method == method and fragment in r.url) as result:
        page.locator(form + ' button[type=submit]').click()
    expect(page.locator(form + ' button[type=submit]')).to_be_enabled()
    return result.value

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={'width': 1440, 'height': 1000})
        # Deliberate provider failure tests recovery only; no provider conformity claim.
        context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(META['url']) else route.abort())
        page = context.new_page()
        page.on('pageerror', lambda error: ERRORS.append(str(error)))
        login(page, 'admin')
        companies = api(page, 'GET', '/empresas')['items']
        company = next(x for x in companies if x['nit'] == 'SGPM-TEST-A')['id']
        other = next(x for x in companies if x['nit'] == 'SGPM-TEST-B')['id']
        project = api(page, 'POST', f'/empresas/{company}/proyectos', {'nombre': 'Recorrido Ñ ' + uuid.uuid4().hex[:8], 'mineral_principal': 'Oro'}, 201)
        project2 = api(page, 'POST', f'/empresas/{company}/proyectos', {'nombre': 'Solicitud propia Ñ', 'mineral_principal': 'Oro'}, 201)
        prefix = f'/proyectos/{project["id"]}'
        api(page, 'POST', prefix + '/expedientes', {'codigo_expediente': 'T-A', 'tipo_expediente': 'TITULO', 'filiacion': 'PROPIO'}, 201)
        api(page, 'POST', f'/proyectos/{project2["id"]}/expedientes', {'codigo_expediente': 'S-A', 'tipo_expediente': 'SOLICITUD', 'filiacion': 'PROPIO'}, 201)
        for index in range(16):
            api(page, 'POST', prefix + '/vecinas', {'nueva_identidad': {'nombre': f'Vecina {index + 1} Ñ'}}, 201)
        page.goto(META['url'] + prefix + '/vecinas')
        expect(page.locator('#neighbor-list tr')).to_have_count(16)
        page.locator('#neighbor-name').fill('Vecina 17 <script>window.xss=1</script>')
        page.locator('#neighbor-notes').fill('Notas exclusivas Ñ')
        result = submit(page, '#neighbor-form', 'POST', '/vecinas')
        check('Create neighbor 17 in UI', result.status == 201)
        neighbor = result.json()['data']
        expect(page.locator('#neighbor-list tr')).to_have_count(17)
        check('Neighbor 17 hatch assigned', neighbor['orden_registro'] == 17 and neighbor['patron_indice'] > 0)
        page.locator('#neighbor-file-identity').select_option(str(neighbor['empresa_vecina_id']))
        page.locator('#neighbor-file-code').fill('S-A')
        page.locator('#neighbor-file-type').select_option('SOLICITUD')
        check('Associate neighboring source in UI', submit(page, '#neighbor-file-form', 'POST', '/expedientes').status == 201)
        responsive(page, 'neighbors')
        page.goto(META['url'] + '/catastro')
        page.locator('#cadastre-codigo').fill('T-A')
        submit(page, '#cadastre-search', 'GET', '/catastro?')
        expect(page.locator('#cadastre-list tr')).to_have_count(1)
        check('Cadastre search UI', 'T-A' in page.locator('#cadastre-list').inner_text())
        responsive(page, 'cadastre')
        page.locator('#cadastre-list a').first.click()
        check('Cadastre 30 attribute detail', page.locator('#cadastre-detail dt').count() == 30)
        responsive(page, 'cadastre-detail')
        page.goto(META['url'] + '/auditoria')
        expect(page.locator('#audit-list tr').first).to_be_visible()
        responsive(page, 'audit')
        page.locator('#audit-list a').first.click()
        check('Audit detail without credential fields', 'password_hash' not in page.locator('main').inner_text())
        responsive(page, 'audit-detail')
        page.goto(META['url'] + prefix + '/mapa')
        page.locator('#module-status').filter(has_text='Carga completa:').wait_for(timeout=30000)
        metrics = page.evaluate('window.sgpmMapMetrics')
        check('Map complete with all features', metrics['rendered'] == metrics['source_total'] == 2)
        check('Google 4 unavailable without key', page.locator('#map-basemap option:disabled').count() == 4)
        check('Own title blue rendered', page.locator('canvas.leaflet-zoom-animated').count() > 0)
        check('Neighbor SVG hatch rendered', page.locator('svg pattern').count() >= 1 and page.locator('svg path[fill^="url"]').count() >= 1)
        page.locator('#map-files button').first.click()
        expect(page.locator('#identify-title')).to_be_focused()
        check('Identify keyboard 30 attributes', page.locator('#identify-attributes dt').count() == 30)
        check('XSS properties remain text', page.evaluate('window.xss !== 1'))
        # Hover requires no network request; use the actual SVG layer.
        requests = []
        page.on('request', lambda r: requests.append(r.url) if '/api/v1/' in r.url else None)
        target = page.locator('svg path[fill^="url"]').first
        target.dispatch_event('mouseover', {'clientX': 100, 'clientY': 100})
        page.wait_for_timeout(100)
        check('Local tooltip without HTTP', not requests and page.locator('.leaflet-tooltip').count() > 0)
        tooltip = page.evaluate('window.sgpmMapMetrics.tooltip_ms')
        check('Tooltip samples below 200ms', bool(tooltip) and max(tooltip) < 200)
        page.locator('#visual-centroid').click()
        expect(page.locator('#measure-status')).to_contain_text('Dibuja un trazo')
        check('Visual centroid needs work trace', True)
        official_before = page.locator('#official-centroid').inner_text()
        def draw_points(points):
            bounds = page.locator('#map').bounding_box()
            for x, y in points:
                page.mouse.move(bounds['x'] + x, bounds['y'] + y)
                page.wait_for_timeout(150)
                page.mouse.click(bounds['x'] + x, bounds['y'] + y)
                page.wait_for_timeout(150)
        page.locator('.leaflet-draw-draw-polyline').click()
        page.locator('#map').scroll_into_view_if_needed()
        draw_points([(500, 150), (650, 220), (800, 150)])
        page.screenshot(path=str(OUT / 'drawing-line.png'))
        page.locator('.leaflet-draw-actions a').filter(has_text='Finish').first.click()
        expect(page.locator('#measure-status')).to_contain_text(' km')
        check('Work line measured in m/km', True)
        page.locator('.leaflet-draw-draw-polygon').click()
        draw_points([(400, 300), (550, 300), (550, 450), (400, 300)])
        expect(page.locator('#measure-status')).to_contain_text(' km²')
        check('Work polygon measured in m²/ha/km²', True)
        page.locator('#visual-centroid').click()
        expect(page.locator('#measure-status')).to_contain_text('Centroide visual auxiliar')
        check('Visual centroid keeps official centroid', page.locator('#official-centroid').inner_text() == official_before)
        page.locator('.leaflet-draw-draw-marker').click()
        draw_points([(300, 200)])
        expect(page.locator('#measure-status')).to_contain_text('Marcador de trabajo')
        check('Work marker added', True)
        page.locator('#map-import-file').set_input_files(ROOT / 'tests/fixtures/imports/point.geojson')
        check('GeoJSON import UI', submit(page, '#map-import-form', 'POST', '/capas/importar').status == 200)
        expect(page.locator('#imported-layers li')).to_have_count(1)
        page.locator('#map-import-file').set_input_files(ROOT / 'tests/fixtures/imports/polygon.kml')
        check('KML import UI', submit(page, '#map-import-form', 'POST', '/capas/importar').status == 200)
        subprocess.run(['docker', 'exec', '--user', 'www-data', META['web_container'], 'php', 'tests/fixtures/browser-shape.php'], check=True, capture_output=True)
        shape_zip = OUT / 'import-shape.zip'
        subprocess.run(['docker', 'cp', META['web_container'] + ':/tmp/sgpm-browser-shape.zip', str(shape_zip)], check=True, capture_output=True)
        page.locator('#map-import-file').set_input_files(shape_zip)
        check('Shapefile ZIP import UI', submit(page, '#map-import-form', 'POST', '/capas/importar').status == 200)
        expect(page.locator('#imported-layers li')).to_have_count(3)
        page.locator('#map-import-file').set_input_files({'name': 'bad.geojson', 'mimeType': 'application/json', 'buffer': b'{bad'})
        check('Invalid import preserves old layers', submit(page, '#map-import-form', 'POST', '/capas/importar').status == 400 and page.locator('#imported-layers li').count() == 3)
        page.locator('#imported-layers input[type=range]').first.fill('0')
        page.locator('#imported-layers input[type=color]').first.fill('#123456')
        responsive(page, 'map')
        for index in range(3):
            page.get_by_role('button', name='Retirar capa', exact=True).first.click()
        expect(page.locator('#imported-layers li')).to_have_count(0)
        check('Temporary layer removed', True)
        (OUT / 'map-metrics.json').write_text(json.dumps({'metrics': metrics, 'tooltip_ms': tooltip}, indent=2), encoding='utf-8')
        page.goto(META['url'] + f'/empresas/{company}/reportes')
        page.locator('#report-format').select_option('CSV')
        response = submit(page, '#report-form', 'POST', '/exportaciones')
        check('Report UI creates asynchronous job', response.status == 202)
        job_id = response.json()['data']['id']
        subprocess.run(['docker', 'exec', '--user', 'www-data', META['web_container'], 'php', 'bin/worker.php', '--env=test', '--job-id=' + str(job_id)], check=True, capture_output=True)
        expect(page.locator('#report-jobs a')).to_be_visible(timeout=30000)
        with page.expect_download() as download:
            page.locator('#report-jobs a').click()
        saved = OUT / 'report.csv'
        download.value.save_as(saved)
        check('Authenticated report downloads', saved.stat().st_size > 0)
        responsive(page, 'reports')
        page.locator('#schedule-link').click()
        page.locator('#schedule-format').select_option('PDF')
        check('Schedule UI default inactive', page.locator('#schedule-state').input_value() == 'INACTIVO')
        schedule_response = submit(page, '#schedule-form', 'POST', '/programaciones')
        check('Schedule create in UI HTTP ' + str(schedule_response.status), schedule_response.status == 201)
        if schedule_response.status != 201:
            print(schedule_response.text(), flush=True)
        responsive(page, 'schedules')
        check('No uncaught JavaScript errors', not ERRORS)
        context.close()
        for role in ['gerente', 'inversionista', 'geologo']:
            context = browser.new_context(viewport={'width': 360, 'height': 1000})
            page = context.new_page()
            login(page, role)
            page.goto(META['url'] + '/empresas')
            expect(page.locator('#company-list tr').first).to_be_visible()
            check(role + ' report controls match role', (page.get_by_role('link', name='Reportes', exact=True).count() > 0) == (role != 'geologo'))
            check(role + ' scoped map B404', page.evaluate('(id) => fetch("/api/v1/empresas/" + id + "/proyectos").then(r => r.status)', other) == 404)
            context.close()
        browser.close()

try:
    run()
except Exception as error:
    ERRORS.append(str(error))
    traceback.print_exc()
finally:
    (OUT / 'results.json').write_text(json.dumps({'checks': CHECKS, 'errors': ERRORS, 'tls_bypass': False, 'external_tiles': 'deliberately blocked; providers not certified'}, ensure_ascii=False, indent=2), encoding='utf-8')
    print(f'{sum(x["pass"] for x in CHECKS)} passed; {sum(not x["pass"] for x in CHECKS)} failed; {len(ERRORS)} errors', flush=True)
    if ERRORS or any(not x['pass'] for x in CHECKS):
        raise SystemExit(1)
