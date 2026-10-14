<?php
declare(strict_types=1);
return function (Sgpm\Http\Router $router,Sgpm\Infrastructure\Configuration $config,string $requestId) {
    foreach (array('/api/v1/auditoria','/api/v1/auditoria/{id}','/api/v1/empresas/{empresa}/auditoria','/api/v1/empresas/{empresa}/auditoria/{id}') as $path) {
        $router->add('GET',$path,function ($params) use ($config,$requestId) {
            return (new Sgpm\Modules\Auditoria\AuditController($config,$requestId))->handle(isset($params['empresa'])?(int)$params['empresa']:0,isset($params['id'])?(int)$params['id']:0);
        });
    }
    foreach (array('/auditoria','/auditoria/{id}','/empresas/{empresa}/auditoria','/empresas/{empresa}/auditoria/{id}') as $path) {
        $router->add('GET',$path,function ($params) use ($config,$requestId) {
            $db=new Sgpm\Infrastructure\Database($config); $context=(new Sgpm\Http\Request($db,$requestId))->context();
            $company=isset($params['empresa'])?(int)$params['empresa']:0; $id=isset($params['id'])?(int)$params['id']:0;
            $repository=new Sgpm\Modules\Auditoria\AuditRepository($db);
            if ($id) { return Sgpm\Http\Page::render('auditoria/detail',array('company'=>$company,'event'=>$repository->find($company,$id,$context))); }
            $repository->listing($company,array('page_size'=>'1'),$context);
            return Sgpm\Http\Page::render('auditoria/index',array('company'=>$company));
        });
    }
};
