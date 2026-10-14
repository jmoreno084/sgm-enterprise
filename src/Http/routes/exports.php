<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    $router->add('GET','/empresas/{id}/reportes',function ($params) use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); $context=(new Sgpm\Http\Request($db,$requestId))->context(); (new Sgpm\Auth\ResourceAccess($db))->company((int)$params['id'],'exportar',$context);
        return Sgpm\Http\Page::render('reportes/index',array('id'=>(int)$params['id']));
    });
    $router->add('POST','/api/v1/empresas/{id}/exportaciones',function ($params) use ($config,$requestId) { return (new Sgpm\Modules\Reportes\ExportController($config,$requestId))->handle('POST',(int)$params['id']); });
    $router->add('GET','/api/v1/exportaciones/{id}',function ($params) use ($config,$requestId) { return (new Sgpm\Modules\Reportes\ExportController($config,$requestId))->handle('GET',(int)$params['id']); });
    $router->add('GET','/api/v1/exportaciones/{id}/archivo',function ($params) use ($config,$requestId) { return (new Sgpm\Modules\Reportes\ExportController($config,$requestId))->handle('GET',(int)$params['id'],true); });
};
