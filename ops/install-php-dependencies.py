"""Reproduce the reviewed PHP vendor package from fixed local source archives."""
import difflib
import hashlib
import json
import re
import urllib.request
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
manifest_path = ROOT / 'ops/dependencies-php.json'
manifest = json.loads(manifest_path.read_text(encoding='utf-8'))
for dependency in manifest['dependencies']:
    archive = ROOT / dependency['archive']
    assert hashlib.sha256(archive.read_bytes()).hexdigest() == dependency['sha256'], 'Source archive checksum mismatch'
    folder = 'tfpdf' if dependency['name'] == 'setasign/tfpdf' else 'php-shapefile'
    destination = ROOT / 'vendor' / folder
    patches = []
    files = []
    with zipfile.ZipFile(archive) as source:
        commit = source.comment.decode('ascii')
        assert re.fullmatch('[a-f0-9]{40}',commit)
        assert dependency.get('commit',commit) == commit
        dependency['commit'] = commit
        dependency['commit_evidence'] = 'GitHub archive ZIP comment; archive SHA256 verified'
        for member in source.namelist():
            if member.endswith('/'):
                continue
            relative = member.split('/', 1)[1]
            if folder == 'tfpdf':
                selected = relative == 'tfpdf.php' or relative.startswith('font/') and (relative.endswith('.php') or relative.endswith('DejaVuSans.ttf') or relative.endswith('DejaVuSans-Bold.ttf') or relative.endswith('DejaVu_LICENSE.txt')) or relative in ['README.txt', 'README.md', 'composer.json']
            else:
                selected = relative.startswith('src/') or relative in ['LICENSE', 'VERSION', 'composer.json', 'README.md']
            if not selected:
                continue
            assert '..' not in Path(relative).parts and not Path(relative).is_absolute()
            original = source.read(member)
            adapted = original
            if relative.endswith('.php'):
                assert original.startswith(b'<?php'), relative
                adapted = b'<?php\ndeclare(strict_types=1);\n' + original[5:].lstrip(b'\r\n')
                patches.extend(difflib.unified_diff(original.decode('utf-8-sig').splitlines(True), adapted.decode('utf-8').splitlines(True), fromfile=folder + '/' + relative, tofile=folder + '/' + relative))
            output = destination / relative
            output.parent.mkdir(parents=True, exist_ok=True)
            if output.exists() and output.read_bytes() not in [original, adapted]:
                raise RuntimeError('Refusing to overwrite modified vendor: ' + relative)
            output.write_bytes(adapted)
            files.append({'path':output.relative_to(ROOT).as_posix(),'original_sha256':hashlib.sha256(original).hexdigest(),'sha256':hashlib.sha256(adapted).hexdigest()})
    patch_path = ROOT / 'ops/patches' / (folder + '-strict-types.patch')
    patch_path.write_bytes(''.join(patches).encode('utf-8'))
    if folder == 'tfpdf':
        license_source = ROOT / 'ops/dependency-sources/LGPL-2.1.txt'
        license_bytes = license_source.read_bytes() if license_source.exists() else urllib.request.urlopen('https://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt',timeout=30).read()
        license_hash = '20e50fe7aae3e56378ebf0417d9de904f55a0e61e4df315333e632a4d3555d95'
        assert hashlib.sha256(license_bytes).hexdigest() == license_hash
        license_source.write_bytes(license_bytes)
        target = destination / 'LICENSE-LGPL-2.1.txt'
        target.write_bytes(license_bytes)
        files.append({'path':target.relative_to(ROOT).as_posix(),'original_sha256':license_hash,'sha256':license_hash})
        dependency['license_files'] = ['vendor/tfpdf/LICENSE-LGPL-2.1.txt','vendor/tfpdf/font/unifont/DejaVu_LICENSE.txt']
    else:
        dependency['license_files'] = ['vendor/php-shapefile/LICENSE']
    dependency['installed'] = True
    dependency['files'] = files
    dependency['patches'] = [{'path':patch_path.relative_to(ROOT).as_posix(),'sha256':hashlib.sha256(patch_path.read_bytes()).hexdigest()}]
    # Actual PHP runtime verification is recorded only after smoke passes.
manifest_path.write_text(json.dumps(manifest,ensure_ascii=False,indent=2) + '\n',encoding='utf-8')
print('Fixed archives verified; selected vendor and strict_types patches reproduced.')
