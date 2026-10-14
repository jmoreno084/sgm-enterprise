<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$sourceOnly = in_array('--source-only', $argv, true);
$failures = array();
if (!$sourceOnly && (PHP_MAJOR_VERSION !== 7 || PHP_MINOR_VERSION !== 0 || PHP_RELEASE_VERSION !== 33)) {
    $failures[] = 'Se requiere PHP 7.0.33; runtime actual: ' . PHP_VERSION;
}
if (!$sourceOnly) {
    foreach (array('pdo_pgsql', 'curl', 'json', 'mbstring', 'dom', 'xmlwriter', 'zip', 'openssl', 'fileinfo') as $extension) {
        if (!extension_loaded($extension)) { $failures[] = 'Falta extensión: ' . $extension; }
    }
}
$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$count = 0;
foreach ($iterator as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (preg_match('~^(?:\.git|\.agents|\.specify|storage|logs)/~', $relative) || $file->getExtension() !== 'php') { continue; }
    ++$count;
    $source = file_get_contents($file->getPathname());
    if (!preg_match('/\A<\?php\s+declare\(strict_types=1\);/', $source)) {
        $failures[] = $relative . ': falta strict_types al inicio';
    }
    $output = array();
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $status);
    if ($status !== 0) { $failures[] = $relative . ': lint falló'; }
    $code = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            $code .= in_array($token[0], array(T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING,
                T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML), true) ? ' ' : $token[1];
        } else {
            $code .= $token;
        }
    }
    // El parser del runtime exacto es la autoridad, estos patrones son diagnósticos adicionales.
    foreach (array('/\?\?=/', '/\bfn\s*\(/', '/:\s*(?:void|iterable|never|mixed)\b/',
        '/\b(?:public|private|protected)\s+(?:static\s+)?(?:string|int|bool|float|array|object)\s+\$/',
        '/\bcatch\s*\([^)]*\|/', '/\b(?:public|private|protected)\s+const\b/',
        '/(?:[(:,]\s*)\?(?:string|int|bool|float|array|[A-Z][A-Za-z_\\\\]*)\b/') as $pattern) {
        if (preg_match($pattern, $code)) { $failures[] = $relative . ': revisar sintaxis posterior a PHP7.0'; break; }
    }
}
$manifest = json_decode(file_get_contents($root . '/public/assets/vendor/manifest.json'), true);
foreach ($manifest['files'] as $asset) {
    $path = $root . '/' . $asset['path'];
    if (!is_file($path) || !hash_equals($asset['sha256'], hash_file('sha256', $path))) {
        $failures[] = 'Checksum de activo inválido: ' . $asset['path'];
    }
    if (isset($asset['patch'])) {
        $patch=$asset['patch']; $patchPath=$root.'/'.$patch['path'];
        if (!is_file($patchPath) || !hash_equals($patch['sha256'],hash_file('sha256',$patchPath))) { $failures[]='Parche JS inválido: '.$patch['path']; }
    }
}
$phpDependencies=json_decode(file_get_contents($root.'/ops/dependencies-php.json'),true);
foreach ($phpDependencies['dependencies'] as $dependency) {
    if (empty($dependency['installed']) || !isset($dependency['commit']) || !preg_match('/^[a-f0-9]{40}$/D',$dependency['commit']) || empty($dependency['license'])) { $failures[]='Dependencia PHP sin trazabilidad: '.$dependency['name']; }
    $archive=$root.'/'.$dependency['archive'];
    if (!is_file($archive) || !hash_equals($dependency['sha256'],hash_file('sha256',$archive))) { $failures[]='Archivo original PHP inválido: '.$dependency['name']; }
    foreach (array_merge($dependency['files'],$dependency['patches']) as $entry) {
        $path=$root.'/'.$entry['path'];
        if (!is_file($path) || !hash_equals($entry['sha256'],hash_file('sha256',$path))) { $failures[]='Vendor/parche PHP inválido: '.$entry['path']; }
    }
}
$jsDependencies=json_decode(file_get_contents($root.'/ops/js-dependencies.json'),true);
foreach ($jsDependencies as $dependency) {
    $archive=$root.'/'.$dependency['archive'];
    if (!is_file($archive) || !hash_equals($dependency['sha256'],hash_file('sha256',$archive)) || empty($dependency['license']) || empty($dependency['git_commit'])) { $failures[]='Dependencia JS sin trazabilidad: '.$dependency['name']; }
}
echo ($sourceOnly ? 'DIAGNOSTICO ESTATICO; NO CERTIFICA PHP7.0.33' : 'VERIFICACION PHP7.0.33') . "\n";
echo $count . " archivos PHP revisados.\n";
foreach ($failures as $failure) { fwrite(STDERR, $failure . "\n"); }
exit(count($failures) > 0 ? 1 : 0);

