"""Prepare fixed browser distributions outside production, with upstream integrity."""
import base64
import hashlib
import io
import importlib.util
import json
import os
import shutil
import subprocess
import tarfile
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PACKAGES = [('leaflet-draw','1.0.4'),('leaflet-measure','3.1.0'),('leaflet.gridlayer.googlemutant','0.13.5'),('esri-leaflet','2.5.3'),('@turf/turf','6.5.0'),('acorn','8.15.0')]
manifest_path = ROOT / 'public/assets/vendor/manifest.json'
manifest = json.loads(manifest_path.read_text(encoding='utf-8'))
recorded_assets = {row['path']:row for row in manifest['files']}
build_node = os.environ.get('SGPM_BUILD_NODE') or shutil.which('node')
if not build_node:
    playwright = importlib.util.find_spec('playwright')
    if playwright and playwright.origin:
        candidate = Path(playwright.origin).parent / 'driver' / ('node.exe' if os.name == 'nt' else 'node')
        if candidate.is_file():
            build_node = str(candidate)
if not build_node:
    raise RuntimeError('Node de desarrollo requerido para reproducir las fábricas estáticas CSP; nunca compilar en producción.')
owned_prefixes = ['public/assets/vendor/' + name.replace('@','').replace('/','-') + '/' for name, _ in PACKAGES if name != 'acorn']
assets = {row['path']:row for row in manifest['files'] if not any(row['path'].startswith(prefix) for prefix in owned_prefixes)}
lock_path = ROOT / 'ops/js-dependencies.json'
previous = {row['name']:row for row in json.loads(lock_path.read_text(encoding='utf-8'))} if lock_path.exists() else {}
lock = []
for name, version in PACKAGES:
    with urllib.request.urlopen('https://registry.npmjs.org/' + name + '/' + version,timeout=30) as response:
        metadata = json.load(response)
    slug = name.replace('@','').replace('/','-')
    archive = ROOT / 'ops/dependency-sources' / (slug + '-' + version + '.tgz')
    data = archive.read_bytes() if archive.exists() else urllib.request.urlopen(metadata['dist']['tarball'],timeout=30).read()
    assert hashlib.sha1(data).hexdigest() == metadata['dist']['shasum']
    integrity = metadata['dist'].get('integrity')
    if integrity:
        algorithm, expected = integrity.split('-',1)
        assert base64.b64encode(hashlib.new(algorithm,data).digest()).decode() == expected
    digest = hashlib.sha256(data).hexdigest()
    if name in previous:
        assert previous[name]['version'] == version and previous[name]['sha256'] == digest
    archive.write_bytes(data)
    lock.append({'name':name,'version':version,'git_commit':metadata.get('gitHead'),'origin':metadata['dist']['tarball'],'license':metadata.get('license'),'archive':archive.relative_to(ROOT).as_posix(),'sha256':digest,'integrity':integrity})
    with tarfile.open(fileobj=io.BytesIO(data),mode='r:gz') as package:
        extracted = []
        for member in package.getmembers():
            if not member.isfile():
                continue
            relative = member.name.split('/',1)[1]
            license_file = any(word in Path(relative).name.lower() for word in ['license','licence','copying']) and not relative.startswith('docs/')
            selected = license_file
            if name == 'leaflet-draw':
                selected |= relative.startswith('dist/') and Path(relative).suffix.lower() in ['.js','.css','.png','.svg']
                selected |= relative in ['package.json','README.md']
            elif name == 'leaflet-measure':
                selected |= relative in ['dist/leaflet-measure.es.js','dist/leaflet-measure.css'] or relative.startswith('dist/assets/')
            elif 'googlemutant' in name:
                selected |= relative == 'dist/Leaflet.GoogleMutant.js'
            elif name == 'esri-leaflet':
                selected |= relative == 'dist/esri-leaflet.js'
            elif name == '@turf/turf':
                selected |= relative == 'turf.min.js'
            elif name == 'acorn':
                selected |= relative == 'dist/acorn.js'
            if not selected:
                continue
            assert '..' not in Path(relative).parts and not Path(relative).is_absolute()
            prefix = 'ops/tools/acorn' if name == 'acorn' else 'public/assets/vendor/' + slug
            target = ROOT / prefix / relative
            content = package.extractfile(member).read()
            adapted_metadata = {}
            if target.exists() and target.read_bytes() != content:
                key = target.relative_to(ROOT).as_posix()
                prior = recorded_assets.get(key,{})
                patch = prior.get('patch',{})
                approved = prior.get('sha256') == hashlib.sha256(target.read_bytes()).hexdigest() and prior.get('original_sha256') == hashlib.sha256(content).hexdigest()
                approved &= bool(patch) and (ROOT / patch['path']).is_file() and hashlib.sha256((ROOT / patch['path']).read_bytes()).hexdigest() == patch['sha256']
                if not approved:
                    raise RuntimeError('Refusing to overwrite modified dependency: ' + str(target))
                content = target.read_bytes()
                adapted_metadata = {key:prior[key] for key in ['original_sha256','patch','csp_profile'] if key in prior}
            target.parent.mkdir(parents=True,exist_ok=True)
            target.write_bytes(content)
            if name != 'acorn':
                key = target.relative_to(ROOT).as_posix()
                assets[key] = {'path':key,'sha256':hashlib.sha256(content).hexdigest(),'origin':metadata['dist']['tarball'],'version':version,'license':metadata.get('license'),'git_commit':metadata.get('gitHead')}
                assets[key].update(adapted_metadata)
            extracted.append(relative)
        print(name,version,'selected:',','.join(extracted))
lock_path.write_text(json.dumps(lock,indent=2) + '\n',encoding='utf-8')
license_source = 'https://raw.githubusercontent.com/Leaflet/Leaflet.draw/65d7866b14e926b72465d0077809683a77344019/MIT-LICENSE.md'
license_archive = ROOT / 'ops/dependency-sources/leaflet-draw-v1.0.4-MIT-LICENSE.md'
license_bytes = license_archive.read_bytes() if license_archive.exists() else urllib.request.urlopen(license_source,timeout=30).read()
license_hash = '320f2c68ac27b7f7f2a78c490470a273d297e753ab37ba026944616bf0a62f47'
assert hashlib.sha256(license_bytes).hexdigest() == license_hash
license_archive.write_bytes(license_bytes)
license_target = ROOT / 'public/assets/vendor/leaflet-draw/MIT-LICENSE.md'
license_target.write_bytes(license_bytes)
key = license_target.relative_to(ROOT).as_posix()
assets[key] = {'path':key,'sha256':license_hash,'origin':license_source,'version':'1.0.4','license':'MIT','git_commit':'65d7866b14e926b72465d0077809683a77344019'}
manifest['files'] = list(assets.values())
manifest_path.write_text(json.dumps(manifest,indent=2) + '\n',encoding='utf-8')
subprocess.run([build_node,str(ROOT / 'ops/adapt-js-csp.cjs')],check=True)
