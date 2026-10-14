<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
require_once dirname(__DIR__).'/fixtures/ModuleHttpsClient.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $server=new SgpmLocalApache(); $clients=array();
    try {
        $server->start();
        foreach (array('admin','gerente','geologo','inversionista') as $role) { $clients[$role]=new SgpmModuleHttpsClient($server); $clients[$role]->login($role.'@example.invalid',getenv('SGPM_FIXTURE_PASSWORD')); }
        $api='/api/v1/empresas/'.$f->a; $admin=$clients['admin']; $manager=$clients['gerente']; $geo=$clients['geologo'];
        $assert($geo->call('POST',$api.'/exportaciones',array('formato'=>'CSV'))['status']===403,'Geólogo no exporta.');
        $assert($manager->call('POST',$api.'/exportaciones',array('formato'=>'CSV'),false,false)['status']===403,'CSRF obligatorio.');
        $assert($manager->call('POST','/api/v1/empresas/'.$f->b.'/exportaciones',array('formato'=>'CSV'))['status']===404,'Empresa ajena oculta.');
        $created=$manager->call('POST',$api.'/exportaciones',array('formato'=>'CSV')); $assert($created['status']===202,'Solicitud asíncrona202.'); $job=$created['json']['data'];
        $assert($manager->call('GET',$job['url_descarga'])['status']===409,'No descarga pendiente.');
        $assert($clients['inversionista']->call('GET',$job['url_estado'])['status']===404,'Otro solicitante oculto incluso con X.');
        $worker=new Sgpm\Jobs\ExportWorker($f->db); $assert($worker->once((int)$job['id']),'Worker procesa el job solicitado.');
        // CLI suite runs as root; Apache drops to www-data. Production runs both
        // workers and Apache under the same service identity (private mode 0600).
        $private=$repo=new Sgpm\Modules\Reportes\ExportRepository($f->db);
        $downloadFile=$private->download($job['id'],$f->manager);
        chown(Sgpm\Modules\Reportes\ExportRepository::directory(),'www-data');
        chown($downloadFile['path'],'www-data');
        $download=$manager->call('GET',$job['url_descarga']); $assert($download['status']===200 && strpos($download['headers'],'attachment; filename="sgpm-exportacion-')!==false && strpos($download['headers'],'text/csv')!==false,'Descarga MIME y nombre seguro.');
        $assert($geo->call('GET','/empresas/'.$f->a.'/reportes')['status']===403 && $manager->call('GET','/empresas/'.$f->a.'/reportes')['status']===200,'SSR respeta X.');
        $assert($manager->call('GET',$api.'/programaciones')['status']===403 && $geo->call('GET','/empresas/'.$f->a.'/programaciones')['status']===403,'Programación solo administrador.');
        $schedule=$admin->call('POST',$api.'/programaciones',array('formato'=>'PDF')); $assert($schedule['status']===201,'Programa inicial201.'); $s=$schedule['json']['data'];
        $assert($admin->call('PUT','/api/v1/programaciones/'.$s['id'],array('version'=>$s['version'],'estado'=>'ACTIVO','destinatarios'=>array($f->manager['usuario_id'])))['status']===200,'Activa con destinatario autorizado.');
        $assert($admin->call('DELETE','/api/v1/programaciones/'.$s['id'],array('version'=>$s['version']))['status']===409,'Versión obsoleta409.');
        $assert($admin->call('DELETE','/api/v1/programaciones/'.$s['id'],array('version'=>$s['version']+1))['status']===204,'Eliminar sin dependencias204.');
        $f->db->execute("UPDATE sgpm.exportacion_job SET expira_en=timezone('UTC',now())-interval '1 second' WHERE id=:id",array('id'=>$job['id']));
        $assert($manager->call('GET',$job['url_descarga'])['status']===410,'Archivo caducado410.'); (new Sgpm\Jobs\MaintenanceWorker($f->db))->once();
    } finally { foreach ($clients as $client) { $client->close(); } $server->close(); }
};
