<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    foreach (array('GET','POST','DELETE') as $method) {
        $router->add($method,'/api/v1/sesion',function () use ($config,$requestId,$method) { return (new Sgpm\Http\AuthController($config,$requestId))->session($method); });
    }
    $router->add('POST','/api/v1/recuperaciones',function () use ($config,$requestId) { return (new Sgpm\Http\AuthController($config,$requestId))->recovery(false); });
    $router->add('POST','/api/v1/recuperaciones/confirmar',function () use ($config,$requestId) { return (new Sgpm\Http\AuthController($config,$requestId))->recovery(true); });
    foreach (array('/'=>'home','/login'=>'login','/recuperar'=>'recovery') as $path=>$template) {
        $router->add('GET',$path,function () use ($template) {
            ob_start(); require dirname(__DIR__,3).'/views/auth/'.$template.'.php';
            return new Sgpm\Http\Response(ob_get_clean(),200,array('Content-Type'=>'text/html; charset=utf-8','Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer'));
        });
    }
};
