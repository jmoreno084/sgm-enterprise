<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    foreach (array('GET','POST') as $method) {
        $router->add($method,'/api/v1/empresas/{id}/programaciones',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Reportes\ScheduleController($config,$requestId))->handle($method,(int)$params['id']); });
    }
    foreach (array('PUT','DELETE') as $method) {
        $router->add($method,'/api/v1/programaciones/{id}',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Reportes\ScheduleController($config,$requestId))->handle($method,(int)$params['id']); });
    }
    $router->add('GET','/empresas/{id}/programaciones',function ($params) use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); $context=(new Sgpm\Http\Request($db,$requestId))->context(); $access=new Sgpm\Auth\ResourceAccess($db); $access->admin($context); $access->company((int)$params['id'],'consultar',$context);
        return Sgpm\Http\Page::render('reportes/schedules',array('id'=>(int)$params['id']));
    });
};
