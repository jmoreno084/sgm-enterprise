<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    foreach (array('GET','POST') as $method) {
        $router->add($method,'/api/v1/empresas/{empresa}/proyectos',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Proyectos\ProjectController($config,$requestId))->handle($method,(int)$params['empresa']); });
        $router->add($method,'/api/v1/proyectos/{id}/expedientes',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Proyectos\ExpedienteController($config,$requestId))->handle($method,(int)$params['id']); });
    }
    foreach (array('GET','PUT','DELETE') as $method) {
        $router->add($method,'/api/v1/proyectos/{id}',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Proyectos\ProjectController($config,$requestId))->handle($method,0,(int)$params['id']); });
    }
    foreach (array('PUT','DELETE') as $method) {
        $router->add($method,'/api/v1/proyectos/{id}/expedientes/{vinculo}',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Proyectos\ExpedienteController($config,$requestId))->handle($method,(int)$params['id'],(int)$params['vinculo']); });
    }
    $router->add('GET','/empresas/{id}/proyectos',function ($params) use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); $context=(new Sgpm\Http\Request($db,$requestId))->context();
        (new Sgpm\Auth\ResourceAccess($db))->company((int)$params['id'],'consultar',$context);
        return Sgpm\Http\Page::render('proyectos/index',array('empresa_id'=>(int)$params['id']));
    });
    $router->add('GET','/proyectos/{id}',function ($params) use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); $context=(new Sgpm\Http\Request($db,$requestId))->context();
        $project=(new Sgpm\Modules\Proyectos\ProjectService($db))->find((int)$params['id'],$context);
        return Sgpm\Http\Page::render('proyectos/expedientes',$project);
    });
};
