<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    foreach (array('GET','POST') as $method) {
        $router->add($method,'/api/v1/usuarios',function () use ($config,$requestId,$method) { return (new Sgpm\Modules\Usuarios\UserController($config,$requestId))->handle($method); });
    }
    foreach (array('GET','PUT','DELETE') as $method) {
        $router->add($method,'/api/v1/usuarios/{id}',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Usuarios\UserController($config,$requestId))->handle($method,(int)$params['id']); });
    }
    foreach (array('GET','POST') as $method) {
        $router->add($method,'/api/v1/empresas/{empresa}/usuarios',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Usuarios\MembershipController($config,$requestId))->handle($method,(int)$params['empresa']); });
    }
    foreach (array('PUT','DELETE') as $method) {
        $router->add($method,'/api/v1/empresas/{empresa}/usuarios/{vinculo}',function ($params) use ($config,$requestId,$method) { return (new Sgpm\Modules\Usuarios\MembershipController($config,$requestId))->handle($method,(int)$params['empresa'],(int)$params['vinculo']); });
    }
    $router->add('GET','/usuarios',function () use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); $request=new Sgpm\Http\Request($db,$requestId); $user=$request->user();
        if (!Sgpm\Auth\Authorization::permits($user,'admin')) { throw new Sgpm\Http\HttpException(403,'SIN_PERMISO','Solo un Administrador puede consultar usuarios.'); }
        ob_start(); require dirname(__DIR__,3).'/views/usuarios/index.php';
        return new Sgpm\Http\Response(ob_get_clean(),200,array('Content-Type'=>'text/html; charset=utf-8','Cache-Control'=>'no-store'));
    });
};
