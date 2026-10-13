<?php
declare(strict_types=1);

class SgpmAmbiguousEmailDouble extends Sgpm\Infrastructure\CorporateEmailAdapter
{
    public $calls=0;
    public function send(string $email,array $payload): array
    {
        ++$this->calls;
        return array('estado'=>'INDETERMINADO','http_status'=>null,'error_codigo'=>'TRANSPORTE_AMBIGUO');
    }
}

return function ($assert) {
    $config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php');
    $config->assertTestDatabase(); $db=new Sgpm\Infrastructure\Database($config);
    $cipher=new Sgpm\Infrastructure\PayloadCipher($config);
    $outbox=new Sgpm\Jobs\NotificationRepository($db,$cipher);
    $admin=(new Sgpm\Modules\Usuarios\UserRepository($db))->credentials('admin@example.invalid');
    $context=array('usuario_id'=>(int)$admin['id'],'ip'=>'127.0.0.6','request_id'=>bin2hex(random_bytes(16)));
    $user=(new Sgpm\Modules\Usuarios\UserService($db,$outbox))->create(array('correo'=>'worker@example.invalid','nombre'=>'Worker','numero_documento'=>'WORKER','password'=>'clave segura para worker'),$context);
    $adapter=new SgpmAmbiguousEmailDouble($config,new Sgpm\Infrastructure\LibCurlModel());
    $worker=new Sgpm\Jobs\NotificationWorker($db,$cipher,$adapter);
    $assert($worker->once(),'Worker procesa pendiente.');
    $row=$db->execute('SELECT estado,payload_cifrado,procesado_en FROM sgpm.notificaciones_outbox WHERE usuario_destino_id=:id',array('id'=>$user['id']))->fetch();
    $assert($row['estado']==='INDETERMINADO' && $row['payload_cifrado']==='' && $row['procesado_en']!==null,'Resultado ambiguo conserva solo metadatos.');
    $assert(!$worker->once() && $adapter->calls===1,'No reenvio automatico tras timeout ambiguo.');
    (new Sgpm\Infrastructure\Transaction($db))->run($context,function () use ($outbox,$user) {
        $outbox->enqueue('BIENVENIDA',$user['id'],array('asunto'=>'Prueba crash','mensaje'=>'Prueba'),'crash:'.$user['id'],gmdate('Y-m-d H:i:s',time()+86400));
    });
    $db->execute("UPDATE sgpm.notificaciones_outbox SET estado='PROCESANDO',disponible_en=timezone('UTC',now())-interval '6 minutes' WHERE deduplicacion=:key",array('key'=>'crash:'.$user['id']));
    $assert(!$worker->once() && $adapter->calls===1,'Worker perdido no provoca otro envio.');
    $row=$db->execute('SELECT estado,payload_cifrado FROM sgpm.notificaciones_outbox WHERE deduplicacion=:key',array('key'=>'crash:'.$user['id']))->fetch();
    $assert($row['estado']==='INDETERMINADO' && $row['payload_cifrado']==='','Crash elimina payload y queda indeterminado.');
};
