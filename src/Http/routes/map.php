<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    $router->add('GET','/api/v1/proyectos/{id}/mapa',function ($params) use ($config,$requestId) { return (new Sgpm\Modules\Visor\MapController($config,$requestId))->handle((int)$params['id']); });
    $router->add('GET','/api/v1/proyectos/{id}/identify/{vinculo}',function ($params) use ($config,$requestId) { return (new Sgpm\Modules\Visor\MapController($config,$requestId))->handle((int)$params['id'],(int)$params['vinculo']); });
    $router->add('POST','/api/v1/proyectos/{id}/capas/importar',function ($params) use ($config,$requestId) { return (new Sgpm\Modules\Visor\ImportController($config,$requestId))->handle((int)$params['id']); });
    $router->add('GET','/proyectos/{id}/mapa',function ($params) use ($config,$requestId) {
        $db=new Sgpm\Infrastructure\Database($config); $context=(new Sgpm\Http\Request($db,$requestId))->context();
        $project=(new Sgpm\Modules\Proyectos\ProjectService($db))->find((int)$params['id'],$context);
        $project['google_browser_key']=getenv('SGPM_GOOGLE_BROWSER_KEY')?:'';
        return Sgpm\Http\Page::render('visor/map',$project);
    });
};
