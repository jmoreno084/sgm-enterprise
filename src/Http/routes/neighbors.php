<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    $router->add('GET','/api/v1/vecinas',function () use ($config,$requestId) { return (new Sgpm\Modules\Vecinas\NeighborController($config,$requestId))->handle('GET'); });
    foreach (array('GET','PUT','DELETE') as $method) {
        $router->add($method,'/api/v1/vecinas/{id}',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Vecinas\NeighborController($config,$requestId))->handle($method,(int)$params['id']); });
    }
    $router->add('GET','/vecinas',function () use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); $context=(new Sgpm\Http\Request($db,$requestId))->context();
        (new Sgpm\Auth\ResourceAccess($db))->admin($context); return Sgpm\Http\Page::render('vecinas/catalog');
    });
};
