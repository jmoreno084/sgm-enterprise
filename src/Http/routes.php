<?php
declare(strict_types=1);

return function (Sgpm\Http\Router $router, Sgpm\Infrastructure\Configuration $configuration, string $requestId) {
    foreach (glob(__DIR__ . '/routes/*.php') as $file) {
        $register = require $file;
        $register($router, $configuration, $requestId);
    }
};

