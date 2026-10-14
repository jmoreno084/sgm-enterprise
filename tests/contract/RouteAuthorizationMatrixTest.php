<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
require_once dirname(__DIR__).'/fixtures/ModuleHttpsClient.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $a=$f->project(); $b=$f->project($f->b);
    $files=new Sgpm\Modules\Proyectos\ExpedienteService($f->db);
    $fa=$files->save($a['id'],0,array('codigo_expediente'=>'T-A','tipo_expediente'=>'TITULO','filiacion'=>'PROPIO'),$f->admin);
    $fb=$files->save($b['id'],0,array('codigo_expediente'=>'T-A','tipo_expediente'=>'TITULO','filiacion'=>'PROPIO'),$f->admin);
    $neighborNit='MATRIX-'.bin2hex(random_bytes(4));
    $neighbor=(new Sgpm\Modules\Vecinas\ProjectNeighborService($f->db))->save($a['id'],0,array('nueva_identidad'=>array('nombre'=>'Matriz Ñ','nit'=>$neighborNit)),$f->admin);
    $nid=$neighbor['empresa_vecina_id'];
    $job=(new Sgpm\Modules\Reportes\ExportRepository($f->db))->create($f->a,array('formato'=>'CSV'),$f->manager);
    $schedule=(new Sgpm\Modules\Reportes\ScheduleService($f->db))->save($f->a,0,array('formato'=>'CSV'),$f->admin);
    $event=(int)$f->db->execute('SELECT id FROM sgpm.auditoria WHERE empresa_id_contexto=:id ORDER BY id DESC LIMIT 1',array('id'=>$f->a))->fetchColumn();
    $member=$f->db->execute('SELECT * FROM sgpm.empresa_usuarios WHERE empresa_id=:company AND usuario_id=:user',array('company'=>$f->a,'user'=>$f->manager['usuario_id']))->fetch();
    $cases=array(); $all=array(200,200,200,200); $admin=array(200,403,403,403); $scope=array(200,404,404,404); $export=array(200,200,403,200);
    $add=function ($method,$template,$url,$statuses,$body=null) use (&$cases) { $cases[]=array($method,$template,$url,$statuses,$body); };
    $read=function ($template,$url,$statuses) use ($add) { $add('GET',$template,$url,$statuses); };
    $read('/api/v1/empresas','/api/v1/empresas',$all);
    $read('/api/v1/empresas/{id}','/api/v1/empresas/'.$f->a,$all);
    $read('/api/v1/empresas/{id}','/api/v1/empresas/'.$f->b,$scope);
    $read('/empresas','/empresas',$all);
    $read('/api/v1/usuarios','/api/v1/usuarios',$admin);
    $read('/api/v1/usuarios/{id}','/api/v1/usuarios/'.$f->manager['usuario_id'],$admin);
    $read('/usuarios','/usuarios',$admin);
    $read('/api/v1/empresas/{empresa}/usuarios','/api/v1/empresas/'.$f->a.'/usuarios',$admin);
    foreach (array($a,$b) as $index=>$project) {
        $p='/api/v1/proyectos/'.$project['id']; $c='/api/v1/empresas/'.$project['empresa_id']; $allowed=$index?$scope:$all;
        foreach (array('/api/v1/proyectos/{id}'=>'','/api/v1/proyectos/{id}/expedientes'=>'/expedientes','/api/v1/proyectos/{id}/vecinas'=>'/vecinas','/api/v1/proyectos/{id}/mapa'=>'/mapa') as $template=>$suffix) { $read($template,$p.$suffix,$allowed); }
        $read('/api/v1/proyectos/{id}/identify/{vinculo}',$p.'/identify/'.($index?$fb['id']:$fa['id']),$allowed);
        $read('/api/v1/empresas/{empresa}/proyectos',$c.'/proyectos',$allowed);
        foreach (array('/proyectos/{id}'=>'','/proyectos/{id}/vecinas'=>'/vecinas','/proyectos/{id}/mapa'=>'/mapa') as $template=>$suffix) { $read($template,'/proyectos/'.$project['id'].$suffix,$allowed); }
        $read('/empresas/{id}/proyectos','/empresas/'.$project['empresa_id'].'/proyectos',$allowed);
    }
    foreach (array('/api/v1/catastro','/catastro') as $path) { $read($path,$path,$all); $read($path.'/{tipo}/{codigo}',$path.'/TITULO/T-A',$all); }
    $read('/api/v1/vecinas','/api/v1/vecinas',$admin); $read('/api/v1/vecinas/{id}','/api/v1/vecinas/'.$nid,$admin); $read('/vecinas','/vecinas',$admin);
    foreach (array('/api/v1','') as $prefix) {
        $read($prefix.'/auditoria',$prefix.'/auditoria',$admin); $read($prefix.'/auditoria/{id}',$prefix.'/auditoria/'.$event,$admin);
        $read($prefix.'/empresas/{empresa}/auditoria',$prefix.'/empresas/'.$f->a.'/auditoria',$export);
        $read($prefix.'/empresas/{empresa}/auditoria/{id}',$prefix.'/empresas/'.$f->a.'/auditoria/'.$event,$export);
        $read($prefix.'/empresas/{empresa}/auditoria',$prefix.'/empresas/'.$f->b.'/auditoria',$scope);
    }
    $read('/api/v1/exportaciones/{id}','/api/v1/exportaciones/'.$job['id'],array(200,200,404,404));
    $read('/api/v1/exportaciones/{id}/archivo','/api/v1/exportaciones/'.$job['id'].'/archivo',array(409,409,404,404));
    $read('/empresas/{id}/reportes','/empresas/'.$f->a.'/reportes',$export);
    $read('/api/v1/empresas/{id}/programaciones','/api/v1/empresas/'.$f->a.'/programaciones',$admin);
    $read('/empresas/{id}/programaciones','/empresas/'.$f->a.'/programaciones',$admin);
    $read('/api/v1/empresas/{id}/logo','/api/v1/empresas/'.$f->a.'/logo',array(404,404,404,404));
    $invalid=array('version'=>999999);
    foreach (array('POST','PUT','DELETE') as $method) {
        $suffix=$method==='POST'?'':'/{id}';
        $add($method,'/api/v1/empresas'.$suffix,'/api/v1/empresas'.($suffix?'/'.$f->a:''),array($method==='POST'?400:409,403,403,403),$invalid);
        $add($method,'/api/v1/usuarios'.$suffix,'/api/v1/usuarios'.($suffix?'/'.$f->manager['usuario_id']:''),array($method==='POST'?400:409,403,403,403),$invalid);
    }
    $add('POST','/api/v1/empresas/{id}/logo','/api/v1/empresas/'.$f->a.'/logo',array(400,403,403,403),$invalid);
    foreach (array('POST','PUT','DELETE') as $method) {
        $suffix=$method==='POST'?'':'/{vinculo}';
        $membershipBody=$method==='PUT'?array_merge($invalid,array('rol'=>'GERENTE','estado'=>'ACTIVO')):$invalid;
        $add($method,'/api/v1/empresas/{empresa}/usuarios'.$suffix,'/api/v1/empresas/'.$f->a.'/usuarios'.($suffix?'/'.$member['id']:''),array($method==='POST'?400:409,403,403,403),$membershipBody);
    }
    $add('POST','/api/v1/empresas/{empresa}/proyectos','/api/v1/empresas/'.$f->a.'/proyectos',array(400,403,400,403),$invalid);
    foreach (array('PUT','DELETE') as $method) { $add($method,'/api/v1/proyectos/{id}','/api/v1/proyectos/'.$a['id'],array(409,403,409,403),$invalid); }
    foreach (array('POST','PUT','DELETE') as $method) {
        $suffix=$method==='POST'?'':'/{vinculo}';
        $add($method,'/api/v1/proyectos/{id}/expedientes'.$suffix,'/api/v1/proyectos/'.$a['id'].'/expedientes'.($suffix?'/'.$fa['id']:''),array($method==='POST'?400:409,403,$method==='POST'?400:409,403),$invalid);
        $suffix=$method==='POST'?'':'/{vecina}';
        $add($method,'/api/v1/proyectos/{id}/vecinas'.$suffix,'/api/v1/proyectos/'.$a['id'].'/vecinas'.($suffix?'/'.$nid:''),array($method==='POST'?400:409,403,$method==='POST'?400:409,403),$invalid);
    }
    $add('POST','/api/v1/proyectos/{id}/vecinas/resolver','/api/v1/proyectos/'.$a['id'].'/vecinas/resolver',array(200,403,200,403),array('nit'=>$neighborNit));
    foreach (array('PUT','DELETE') as $method) { $add($method,'/api/v1/vecinas/{id}','/api/v1/vecinas/'.$nid,array(409,403,403,403),$invalid); }
    $add('POST','/api/v1/proyectos/{id}/capas/importar','/api/v1/proyectos/'.$a['id'].'/capas/importar',array(400,400,400,400),$invalid);
    $add('POST','/api/v1/empresas/{id}/exportaciones','/api/v1/empresas/'.$f->a.'/exportaciones',array(400,400,403,400),$invalid);
    $add('POST','/api/v1/empresas/{id}/programaciones','/api/v1/empresas/'.$f->a.'/programaciones',array(400,403,403,403),$invalid);
    foreach (array('PUT','DELETE') as $method) { $add($method,'/api/v1/programaciones/{id}','/api/v1/programaciones/'.$schedule['id'],array(409,403,403,403),$invalid); }
    $coverage=array(); foreach ($cases as $case) { $coverage[$case[0].' '.$case[1]]=true; }
    $router=new Sgpm\Http\Router(); $register=require dirname(__DIR__,2).'/src/Http/routes.php'; $register($router,$f->config,bin2hex(random_bytes(16)));
    foreach ($router->definitions() as $definition) {
        $path=$definition['path'];
        if (in_array($path,array('/','/login','/recuperar','/api/v1/sesion','/api/v1/recuperaciones','/api/v1/recuperaciones/confirmar'),true)) { continue; } // AuthHttp/Recovery/AuthHttps suites.
        $assert(isset($coverage[$definition['method'].' '.$path]),'Endpoint sin censo RBAC: '.$definition['method'].' '.$path);
    }
    $server=new SgpmLocalApache(); $clients=array();
    try {
        $server->start();
        foreach (array('admin','gerente','geologo','inversionista') as $index=>$role) { $client=new SgpmModuleHttpsClient($server); $clients[]=$client; $client->login($role.'@example.invalid',getenv('SGPM_FIXTURE_PASSWORD'));
            foreach ($cases as $case) { $result=$client->call($case[0],$case[2],$case[4]===null?array():$case[4]); $assert($result['status']===$case[3][$index],$role.' '.$case[0].' '.$case[1].': '.$result['status'].' esperado '.$case[3][$index]); }
        }
        $transaction=new Sgpm\Infrastructure\Transaction($f->db);
        try {
            $transaction->run($f->admin,function ($db) use ($member) { $db->execute("UPDATE sgpm.empresa_usuarios SET estado='INACTIVO',version=version+1 WHERE id=:id",array('id'=>(int)$member['id'])); });
            foreach (array('/api/v1/empresas/'.$f->a,'/api/v1/proyectos/'.$a['id'],'/api/v1/proyectos/'.$a['id'].'/mapa','/api/v1/proyectos/'.$a['id'].'/identify/'.$fa['id'],'/api/v1/exportaciones/'.$job['id'],'/api/v1/exportaciones/'.$job['id'].'/archivo','/api/v1/empresas/'.$f->a.'/auditoria') as $path) { $assert($clients[1]->call('GET',$path)['status']===404,'Revocación revalidada: '.$path); }
        } finally { $transaction->run($f->admin,function ($db) use ($member) { $db->execute('UPDATE sgpm.empresa_usuarios SET estado=:state,version=version+1 WHERE id=:id',array('id'=>(int)$member['id'],'state'=>$member['estado'])); }); }
    } finally { foreach ($clients as $client) { $client->close(); } $server->close(); }
};
