<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    $router->add('GET','/api/v1/catastro',function () use ($config,$requestId) { return (new Sgpm\Modules\Catastro\CadastreController($config,$requestId))->handle(); });
    $constraints=array('tipo'=>'TITULO|SOLICITUD','codigo'=>'[^/]+');
    $router->add('GET','/api/v1/catastro/{tipo}/{codigo}',function ($params) use ($config,$requestId) {
        return (new Sgpm\Modules\Catastro\CadastreController($config,$requestId))->handle($params['tipo'],rawurldecode($params['codigo']));
    },$constraints);
    $router->add('GET','/catastro',function () use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); (new Sgpm\Http\Request($db,$requestId))->user(); return Sgpm\Http\Page::render('catastro/index');
    });
    $router->add('GET','/catastro/{tipo}/{codigo}',function ($params) use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); (new Sgpm\Http\Request($db,$requestId))->user();
        $data=(new Sgpm\Modules\Catastro\CadastreRepository($db))->find($params['tipo'],rawurldecode($params['codigo']));
        return Sgpm\Http\Page::render('catastro/detail',$data);
    },$constraints);
};
