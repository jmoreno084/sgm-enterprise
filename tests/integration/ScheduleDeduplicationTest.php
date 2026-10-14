<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $service=new Sgpm\Modules\Reportes\ScheduleService($f->db);
    $f->expect(403,function () use ($service,$f) { $service->save($f->a,0,array('formato'=>'CSV'),$f->manager); });
    $f->expect(400,function () use ($service,$f) { $service->save($f->a,0,array('formato'=>'CSV','estado'=>'ACTIVO','destinatarios'=>array($f->geo['usuario_id'])),$f->admin); });
    $inactive=$service->save($f->a,0,array('formato'=>'CSV'),$f->admin);
    $assert($inactive['estado']==='INACTIVO' && $inactive['hora_local']==='09:00:00' && $inactive['zona']==='America/Bogota','Programación inicial inactiva, 09:00 Bogotá.');
    $schedule=$service->save($f->a,0,array('formato'=>'CSV','estado'=>'ACTIVO','destinatarios'=>array($f->manager['usuario_id'],$f->manager['usuario_id'])),$f->admin);
    $scheduler=new Sgpm\Jobs\ReportScheduler($f->db); $before=new DateTimeImmutable('2026-10-13 13:59:59',new DateTimeZone('UTC')); $after=new DateTimeImmutable('2026-10-13 14:00:00',new DateTimeZone('UTC'));
    $assert($scheduler->once($before)===0 && $scheduler->once($after)===1 && $scheduler->once($after)===0,'Diario 09:00 Bogotá; deduplicación por fecha-programa-usuario.');
    $f->expect(409,function () use ($service,$schedule,$f) { $service->delete($schedule['id'],$schedule['version'],$f->admin); });
    $cipher=new Sgpm\Infrastructure\PayloadCipher($f->config); $outbox=new Sgpm\Jobs\NotificationRepository($f->db,$cipher);
    $exports=new Sgpm\Jobs\ExportWorker($f->db,new Sgpm\Modules\Reportes\ReportNotifications($outbox,$f->config->get('base_url')));
    $job=$f->db->execute('SELECT * FROM sgpm.exportacion_job WHERE programacion_id=:id',array('id'=>$schedule['id']))->fetch();
    while ($job['estado']==='PENDIENTE') { $assert($exports->once((int)$job['id']),'Worker procesa el reporte programado.'); $job=$f->db->execute('SELECT * FROM sgpm.exportacion_job WHERE id=:id',array('id'=>(int)$job['id']))->fetch(); }
    $assert($job['estado']==='LISTO','Reporte programado publicado.');
    $notification=$f->db->execute('SELECT * FROM sgpm.notificaciones_outbox WHERE deduplicacion=:key',array('key'=>'reporte:'.$job['id'].':'.$job['usuario_id']))->fetch();
    $payload=$cipher->decrypt($notification['payload_cifrado']); $assert(strpos($payload['mensaje'],'/api/v1/exportaciones/'.$job['id'].'/archivo')!==false && !isset($payload['adjuntos']),'Enlace autenticado sin adjuntos inventados.');
    $worker=new Sgpm\Jobs\NotificationWorker($f->db,$cipher,new Sgpm\Infrastructure\CorporateEmailAdapter($f->config,new Sgpm\Infrastructure\LibCurlModel()));
    while ($worker->once()) {}
    $sent=$f->db->execute('SELECT * FROM sgpm.notificaciones_outbox WHERE id=:id',array('id'=>(int)$notification['id']))->fetch();
    $assert($sent['estado']==='ACEPTADO' && $sent['error_codigo']==='DOBLE_LOCAL' && $sent['payload_cifrado']==='','Un despacho al doble y payload eliminado.');
    $path=Sgpm\Modules\Reportes\ExportRepository::path($job['archivo_relativo']);
    $f->db->execute("UPDATE sgpm.exportacion_job SET expira_en=timezone('UTC',now())-interval '1 second' WHERE id=:id",array('id'=>(int)$job['id']));
    (new Sgpm\Jobs\MaintenanceWorker($f->db))->once();
    $assert(!is_file($path) && (new Sgpm\Modules\Reportes\ExportRepository($f->db))->internal((int)$job['id'])['estado']==='EXPIRADO','Mantenimiento elimina archivo caducado.');
    $service->save($f->a,$schedule['id'],array('version'=>$schedule['version'],'estado'=>'INACTIVO'),$f->admin);
    $service->delete($inactive['id'],$inactive['version'],$f->admin);
};
