<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$requestId = bin2hex(random_bytes(16));
$logger = new Sgpm\Infrastructure\Logger(dirname(__DIR__) . '/logs');
$errors = new Sgpm\Http\ErrorHandler($logger, $requestId);
$errors->register();
try {
    $configFile = dirname(__DIR__) . '/config/config.php';
    if (!is_file($configFile)) {
        throw new Sgpm\Http\HttpException(503, 'CONFIGURACION_PENDIENTE', 'El servicio está pendiente de configuración.');
    }
    $configuration = new Sgpm\Infrastructure\Configuration(require $configFile);
    $router = new Sgpm\Http\Router();
    $register = require dirname(__DIR__) . '/src/Http/routes.php';
    $register($router, $configuration, $requestId);
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'])->send();
} catch (Throwable $error) {
    $errors->handle($error);
}

