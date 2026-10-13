<?php
declare(strict_types=1);

return function ($assert) {
    $config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php');
    $config->assertTestDatabase(); $db=new Sgpm\Infrastructure\Database($config);
    $repository=new Sgpm\Modules\Usuarios\UserRepository($db);
    $admin=$repository->credentials('admin@example.invalid');
    $context=array('usuario_id'=>(int)$admin['id'],'ip'=>'127.0.0.2','request_id'=>bin2hex(random_bytes(16)));
    $outbox=new Sgpm\Jobs\NotificationRepository($db,new Sgpm\Infrastructure\PayloadCipher($config));
    $users=new Sgpm\Modules\Usuarios\UserService($db,$outbox);
    $expect=function ($status,callable $operation) use ($assert) {
        try { $operation(); throw new RuntimeException('Se esperaba HTTP '.$status); }
        catch (Sgpm\Http\HttpException $error) { $assert($error->status()===$status,'HTTP '.$status.' esperado.'); }
    };
    $expect(409,function () use ($users,$admin,$context) { $users->update((int)$admin['id'],array('version'=>(int)$admin['version'],'estado'=>'INACTIVO'),$context); });
    $auth=new Sgpm\Auth\AuthenticationService($db);
    $expect(401,function () use ($auth,$context) { $auth->login('admin@example.invalid','incorrecta',$context); });
    $identity=$auth->login('admin@example.invalid',getenv('SGPM_FIXTURE_PASSWORD'),$context);
    $middleware=new Sgpm\Http\AuthorizationMiddleware($db);
    $assert((int)$middleware->user($identity)['id']===(int)$admin['id'],'Login valido.');
    $geologist=$repository->credentials('geologo@example.invalid');
    $geoContext=$context; $geoContext['usuario_id']=(int)$geologist['id'];
    $expect(403,function () use ($users,$geoContext) { $users->requireAdmin($geoContext); });
    $a=(int)$db->execute("SELECT id FROM sgpm.empresa WHERE nit='SGPM-TEST-A'")->fetchColumn();
    $b=(int)$db->execute("SELECT id FROM sgpm.empresa WHERE nit='SGPM-TEST-B'")->fetchColumn();
    $assert($middleware->requireCompany($geologist,$a,'editar')['id']==$a,'Geologo edita empresa propia.');
    $expect(403,function () use ($middleware,$geologist,$a) { $middleware->requireCompany($geologist,$a,'exportar'); });
    $expect(404,function () use ($middleware,$geologist,$b) { $middleware->requireCompany($geologist,$b,'consultar'); });
    $membership=$db->execute('SELECT * FROM sgpm.empresa_usuarios WHERE empresa_id=:empresa AND usuario_id=:usuario',array('empresa'=>$a,'usuario'=>(int)$geologist['id']))->fetch();
    $members=new Sgpm\Modules\Usuarios\MembershipService($db,$users);
    $members->save($a,(int)$membership['id'],array('version'=>(int)$membership['version'],'rol'=>'GEOLOGO','estado'=>'INACTIVO'),$context);
    $expect(404,function () use ($middleware,$geologist,$a) { $middleware->requireCompany($geologist,$a,'consultar'); });
    $members->save($a,(int)$membership['id'],array('version'=>(int)$membership['version']+1,'rol'=>'GEOLOGO','estado'=>'ACTIVO'),$context);
    $created=$users->create(array('correo'=>'nuevo@example.invalid','nombre'=>'Nuevo','numero_documento'=>'NEW','password'=>'clave segura para pruebas','estado'=>'ACTIVO'),$context);
    $assert(!isset($created['password_hash'],$created['auth_version']),'Usuario creado sin secretos.');
    $expect(409,function () use ($users,$context) { $users->create(array('correo'=>' NUEVO@EXAMPLE.INVALID ','nombre'=>'Duplicado','numero_documento'=>'DUP','password'=>'clave segura para pruebas'),$context); });
    $newIdentity=$auth->login('nuevo@example.invalid','clave segura para pruebas',$context);
    $updated=$users->update($created['id'],array('version'=>$created['version'],'estado'=>'INACTIVO'),$context);
    $expect(401,function () use ($middleware,$newIdentity) { $middleware->user($newIdentity); });
    $expect(409,function () use ($users,$created,$context) { $users->update($created['id'],array('version'=>$created['version'],'nombre'=>'Obsoleto'),$context); });
    $expect(409,function () use ($users,$updated,$context) { $users->delete($updated['id'],$updated['version'],$context); });
    $worker=new Sgpm\Jobs\NotificationWorker($db,new Sgpm\Infrastructure\PayloadCipher($config),new Sgpm\Infrastructure\CorporateEmailAdapter($config,new Sgpm\Infrastructure\LibCurlModel()));
    $assert($worker->once(),'Worker reclama bienvenida.');
    $row=$db->execute('SELECT estado,payload_cifrado FROM sgpm.notificaciones_outbox WHERE usuario_destino_id=:id',array('id'=>$created['id']))->fetch();
    $assert($row['estado']==='FALLIDO' && $row['payload_cifrado']==='','Destinatario inactivo no recibe bienvenida.');
    $assert(!$worker->once(),'No reenvia filas terminadas.');
    $auth->logout($identity,$context);
    $events=$db->execute("SELECT accion FROM sgpm.auditoria WHERE entidad='usuario' AND entidad_id=:id AND accion IN ('LOGIN','LOGOUT') ORDER BY id",array('id'=>(int)$admin['id']))->fetchAll(PDO::FETCH_COLUMN);
    $assert(in_array('LOGIN',$events,true) && in_array('LOGOUT',$events,true),'Eventos de acceso auditados.');
};
