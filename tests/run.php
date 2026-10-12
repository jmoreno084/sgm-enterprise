<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$assert = function ($condition, string $message) {
    if (!$condition) { throw new RuntimeException($message); }
};
$files = glob(__DIR__ . '/unit/*Test.php');
if (in_array('--integration', $argv, true)) {
    $files = array_merge($files, glob(__DIR__ . '/integration/*Test.php'));
}
$passed = 0;
$failed = 0;
foreach ($files as $file) {
    try {
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

