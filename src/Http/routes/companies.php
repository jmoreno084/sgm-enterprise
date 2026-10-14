<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    foreach (array('GET','POST') as $method) {
        $router->add($method,'/api/v1/empresas',function () use ($config,$requestId,$method) { return (new Sgpm\Modules\Empresas\CompanyController($config,$requestId))->handle($method); });
    }
    foreach (array('GET','PUT','DELETE') as $method) {
        $router->add($method,'/api/v1/empresas/{id}',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Empresas\CompanyController($config,$requestId))->handle($method,(int)$params['id']); });
    }
    foreach (array('GET','POST') as $method) {
        $router->add($method,'/api/v1/empresas/{id}/logo',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Empresas\CompanyController($config,$requestId))->logo($method,(int)$params['id']); });
    }
    $router->add('GET','/empresas',function () use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); (new Sgpm\Http\Request($db,$requestId))->user();
        return Sgpm\Http\Page::render('empresas/index');
    });
};
