<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $p=$f->project();
    (new Sgpm\Modules\Proyectos\ProjectService($f->db))->delete($p['id'],1,$f->admin);
    $cipher=new Sgpm\Infrastructure\PayloadCipher($f->config); $outbox=new Sgpm\Jobs\NotificationRepository($f->db,$cipher);
    $producer=new Sgpm\Jobs\AuditAlertProducer($f->db,$outbox); $clock=new DateTimeImmutable('+2 minutes',new DateTimeZone('UTC'));
    $assert($producer->once($clock)>0,'Borrados generan resumen a administradores activos.');
    $assert($producer->once($clock)===0,'Segundo productor no duplica minuto/destinatario.');
    $rows=$f->db->execute("SELECT * FROM sgpm.notificaciones_outbox WHERE tipo='ALERTA_AUDITORIA'")->fetchAll();
    foreach ($rows as $row) {
        $assert((int)$row['usuario_destino_id']===$f->admin['usuario_id'],'Solo administrador activo.');
        $payload=$cipher->decrypt($row['payload_cifrado']);
        $assert(strpos(json_encode($payload),getenv('SGPM_FIXTURE_PASSWORD'))===false && strpos(json_encode($payload),'password_hash')===false,'Resumen sin secretos.');
    }
    $before=(int)$f->db->execute('SELECT count(*) FROM sgpm.auditoria')->fetchColumn();
    $worker=new Sgpm\Jobs\NotificationWorker($f->db,$cipher,new Sgpm\Infrastructure\CorporateEmailAdapter($f->config,new Sgpm\Infrastructure\LibCurlModel()));
    while ($worker->once()) { }
    $assert((int)$f->db->execute('SELECT count(*) FROM sgpm.auditoria')->fetchColumn()===$before,'Procesar correo no genera recursion de auditoria.');
    $assert($producer->once($clock)===0,'Outbox procesada no se reenvia.');
    $users=new Sgpm\Modules\Usuarios\UserService($f->db,$outbox);
    $former=$users->create(array('correo'=>'former-'.bin2hex(random_bytes(5)).'@example.invalid','nombre'=>'Administrador revocado','numero_documento'=>'FORMER','password'=>'clave segura para alertas','es_administrador'=>true),$f->admin);
    $producer->once($clock);
    $users->update($former['id'],array('version'=>$former['version'],'es_administrador'=>false),$f->admin);
    while ($worker->once()) { }
    $rejected=$f->db->execute("SELECT estado,payload_cifrado FROM sgpm.notificaciones_outbox WHERE tipo='ALERTA_AUDITORIA' AND usuario_destino_id=:id",array('id'=>$former['id']))->fetchAll();
    $assert(count($rejected)>0,'Hay alerta pendiente de administrador luego revocado.');
    foreach ($rejected as $row) { $assert($row['estado']==='FALLIDO' && $row['payload_cifrado']==='','Permiso admin revalidado antes del envio.'); }
};
