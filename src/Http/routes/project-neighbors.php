<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    foreach (array('GET','POST') as $method) {
        $router->add($method,'/api/v1/proyectos/{id}/vecinas',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Vecinas\ProjectNeighborController($config,$requestId))->handle($method,(int)$params['id']); });
    }
    $router->add('POST','/api/v1/proyectos/{id}/vecinas/resolver',function ($params) use ($config,$requestId) { return (new Sgpm\Modules\Vecinas\ProjectNeighborController($config,$requestId))->handle('POST',(int)$params['id'],0,true); });
    foreach (array('PUT','DELETE') as $method) {
        $router->add($method,'/api/v1/proyectos/{id}/vecinas/{vecina}',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Vecinas\ProjectNeighborController($config,$requestId))->handle($method,(int)$params['id'],(int)$params['vecina']); });
    }
    $router->add('GET','/proyectos/{id}/vecinas',function ($params) use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); $context=(new Sgpm\Http\Request($db,$requestId))->context();
        $project=(new Sgpm\Modules\Proyectos\ProjectService($db))->find((int)$params['id'],$context);
        return Sgpm\Http\Page::render('vecinas/project',$project);
    });
};
