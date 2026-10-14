"""Render full official geometry with verified HTTPS and a documented CDP network."""
import json
import argparse
import re
import subprocess
import time
from pathlib import Path
from playwright.sync_api import sync_playwright
from client_limits import reference_limits

ROOT = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser()
parser.add_argument('--metadata', default='storage/acceptance/local.json')
parser.add_argument('--output', default='performance-browser')
parser.add_argument('--external-network', action='store_true')
ARGS = parser.parse_args()
metadata = (ROOT / ARGS.metadata).resolve()
assert metadata.is_relative_to(ROOT / 'storage/acceptance') and re.fullmatch('[a-z0-9-]+', ARGS.output)
ENV = json.loads(metadata.read_text(encoding='utf-8-sig'))
META = json.loads((metadata.parent / 'performance-sessions.json').read_text(encoding='utf-8'))
assert re.fullmatch('https://localhost:[0-9]{4,5}', ENV['url']) and ENV['web_container'].startswith('sgpm-foundation-')
CLIENT_LIMITS = reference_limits()
NETWORK = {'mbps': 20, 'rtt_ms': 50, 'method': 'target netem 25ms/20Mbps plus browser CDP25ms' if ARGS.external_network else 'CDP'}
OUT = ROOT / 'ops/evidence' / ARGS.output
OUT.mkdir(parents=True, exist_ok=True)
RESULTS = []
ERRORS = []
IMAGE = subprocess.run(['docker', 'inspect', ENV['web_container'], '--format', '{{.Image}}'], check=True, capture_output=True, text=True).stdout.strip()
def network_active():
    if not ARGS.external_network:
        return True
    state = subprocess.run(['docker', 'run', '--rm', '--pull=never', '--network', 'container:' + ENV['database_container'], '--entrypoint', '/sbin/tc', IMAGE, 'qdisc', 'show', 'dev', 'eth0'], check=True, capture_output=True, text=True).stdout
    return 'netem' in state and re.search(r'delay\s+25(?:\.0+)?ms', state) and re.search(r'rate\s+20Mbit', state)
try:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        for cache in ['cold-controlled', 'warm']:
            for round_number in range(1, 4):
                context = browser.new_context(viewport={'width': 1440, 'height': 1000})
                token = META['users'][10]['cookie'].split('=', 1)[1]
                context.add_cookies([{'name': 'SGPMSESSID', 'value': token, 'domain': 'localhost', 'path': '/', 'secure': True, 'httpOnly': True, 'sameSite': 'Lax'}])
                page = context.new_page()
                page.on('pageerror', lambda error: ERRORS.append(str(error)))
                cdp = context.new_cdp_session(page)
                cdp.send('Network.enable')
                cdp.send('Network.setBlockedURLs', {'urls': ['*tile.openstreetmap.org*', '*arcgisonline.com*', '*maps.googleapis.com*', '*maps.gstatic.com*']})
                cdp.send('Network.setCacheDisabled', {'cacheDisabled': cache == 'cold-controlled'})
                cdp.send('Network.emulateNetworkConditions', {'offline': False, 'latency': 25 if ARGS.external_network else 50, 'downloadThroughput': -1 if ARGS.external_network else 2500000, 'uploadThroughput': -1 if ARGS.external_network else 2500000, 'connectionType': 'ethernet'})
                if cache == 'warm':
                    page.goto(ENV['url'] + '/proyectos/' + str(META['projects']['500']) + '/mapa')
                    page.locator('#module-status').filter(has_text='Carga completa:').wait_for(timeout=120000)
                for size in [500, 5000]:
                    if not network_active():
                        raise RuntimeError('External network profile is not active; do not qualify this run.')
                    started = time.perf_counter()
                    page.goto(ENV['url'] + '/proyectos/' + str(META['projects'][str(size)]) + '/mapa')
                    page.locator('#module-status').filter(has_text='Carga completa:').wait_for(timeout=120000)
                    elapsed = (time.perf_counter() - started) * 1000
                    metrics = page.evaluate('window.sgpmMapMetrics')
                    page.locator('#map').scroll_into_view_if_needed()
                    bounds = page.locator('#map').bounding_box()
                    point = metrics['first_hover_point']
                    page.mouse.move(bounds['x'] + point['x'], bounds['y'] + point['y'])
                    page.wait_for_timeout(100)
                    metrics = page.evaluate('window.sgpmMapMetrics')
                    page.locator('#map-files button').first.focus()
                    record = {'cache': cache, 'round': round_number, 'polygons': size, 'expected_vertices': META['vertices'][str(size)], 'navigation_to_complete_ms': elapsed, 'map_metrics': metrics, 'threshold_ms': 5000 if size == 500 else 8000}
                    record['complete'] = metrics['rendered'] == metrics['source_total'] == size and metrics['vertices'] == META['vertices'][str(size)]
                    record['tooltip_pass'] = bool(metrics['tooltip_ms']) and max(metrics['tooltip_ms']) < 200
                    record['network_verified'] = bool(network_active())
                    record['resources'] = page.evaluate("performance.getEntriesByType('resource').filter(r => r.initiatorType === 'fetch').map(r => ({duration_ms:r.duration,encoded_bytes:r.encodedBodySize,decoded_bytes:r.decodedBodySize}))")
                    record['pass'] = record['complete'] and elapsed < record['threshold_ms'] and record['tooltip_pass'] and record['network_verified']
                    RESULTS.append(record)
                    print(('PASS ' if record['pass'] else 'FAIL ') + f'{cache} {round_number} polygons={size} complete={metrics["rendered"]} {elapsed:.1f}ms', flush=True)
                    page.screenshot(path=str(OUT / f'{cache}-{round_number}-{size}.png'))
                    (OUT / 'results.json').write_text(json.dumps({'runs': RESULTS, 'errors': ERRORS, 'tls_verified': True, 'network': NETWORK, 'client_limits': CLIENT_LIMITS, 'database_cache': 'retained; cold-controlled refers to browser only'}, indent=2), encoding='utf-8')
                context.close()
        browser.close()
except Exception as error:
    ERRORS.append(str(error))
    print(str(error), flush=True)
finally:
    (OUT / "results.json").write_text(json.dumps({"runs": RESULTS, "errors": ERRORS, "tls_verified": True, "network": NETWORK, "client_limits": CLIENT_LIMITS, "database_cache": "retained; cold-controlled refers to browser only"}, indent=2), encoding="utf-8")

if ERRORS or any(not r['pass'] for r in RESULTS):
    raise SystemExit(1)
