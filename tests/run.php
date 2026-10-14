<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$only=array();
foreach (array_slice($argv, 1) as $option) {
    if (preg_match('/^--only=([A-Za-z][A-Za-z0-9]*Test\.php)$/D',$option,$match)) { $only[]=$match[1]; continue; }
    if (!in_array($option, array('--integration', '--contract'), true)) {
        fwrite(STDERR, "Opcion no soportada. Usa --integration y/o --contract.\n");
        exit(2);
    }
}

$assert = function ($condition, string $message) {
    if (!$condition) { throw new RuntimeException($message); }
};
$files = glob(__DIR__ . '/unit/*Test.php');
if (in_array('--integration', $argv, true)) {
    $files = array_merge($files, glob(__DIR__ . '/integration/*Test.php'));
}
if (in_array('--contract', $argv, true)) {
    $files = array_merge($files, glob(__DIR__ . '/contract/*Test.php'));
}
if ($only) {
    $files=array_values(array_filter($files,function ($file) use ($only) { return in_array(basename($file),$only,true); }));
    if (count($files)!==count(array_unique($only))) { fwrite(STDERR,"Prueba seleccionada no encontrada en los grupos solicitados.\n"); exit(2); }
}
$testDatabase=null;
if ((in_array('--integration',$argv,true) || in_array('--contract',$argv,true)) && is_file(dirname(__DIR__).'/config/config.php')) {
    $testConfiguration=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__).'/config/config.php');
    $testConfiguration->assertTestDatabase();
    $testDatabase=new Sgpm\Infrastructure\Database($testConfiguration);
}
$passed = 0;
$failed = 0;
foreach ($files as $file) {
    try {
        // Login throttles are shared by email across HTTP suites. Reset only in
        // the guarded disposable test database, before each independent suite.
        if ($testDatabase!==null) { $testDatabase->execute('DELETE FROM sgpm.limites_acceso'); }
        $test = require $file;
        $test($assert);
        echo 'PASS ' . basename($file) . "\n";
        ++$passed;
    } catch (Throwable $error) {
        echo 'FAIL ' . basename($file) . ': ' . $error->getMessage() . "\n";
        ++$failed;
    }
}
echo $passed . ' passed; ' . $failed . " failed; PHP " . PHP_VERSION . "\n";
exit($failed === 0 ? 0 : 1);

