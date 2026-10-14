<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $repo=new Sgpm\Modules\Reportes\ExportRepository($f->db); $worker=new Sgpm\Jobs\ExportWorker($f->db);
    $job=$repo->create($f->a,array('formato'=>'CSV','filtros'=>array('codigos'=>array('T-A'))),$f->manager);
    while ($repo->find($job['id'],$f->manager)['estado']==='PENDIENTE') { $assert($worker->once((int)$job['id']),'Worker tiene trabajo.'); }
    $ready=$repo->find($job['id'],$f->manager); $assert($ready['estado']==='LISTO','Publicación atómica LISTO.');
    $file=$repo->download($job['id'],$f->manager); $assert(is_file($file['path']) && strpos($file['path'],'/public/')===false,'Archivo privado.');
    $revoked=$repo->create($f->a,array('formato'=>'CSV'),$f->manager);
    $member=$f->db->execute('SELECT * FROM sgpm.empresa_usuarios WHERE empresa_id=:empresa AND usuario_id=:usuario',array('empresa'=>$f->a,'usuario'=>$f->manager['usuario_id']))->fetch();
    $transaction=new Sgpm\Infrastructure\Transaction($f->db);
    try {
        $transaction->run($f->admin,function ($db) use ($member) { $db->execute("UPDATE sgpm.empresa_usuarios SET estado='INACTIVO',version=version+1 WHERE id=:id",array('id'=>(int)$member['id'])); });
        $f->expect(404,function () use ($repo,$job,$f) { $repo->download($job['id'],$f->manager); });
        while ($repo->internal($revoked['id'])['estado']==='PENDIENTE') { $assert($worker->once((int)$revoked['id']),'Worker procesa revocación.'); }
        $denied=$repo->internal($revoked['id']); $assert($denied['estado']==='FALLIDO' && $denied['archivo_relativo']===null && $denied['error_codigo']==='ACCESO_REVOCADO','No genera archivo tras revocación.');
    } finally {
        $transaction->run($f->admin,function ($db) use ($member) { $db->execute('UPDATE sgpm.empresa_usuarios SET estado=:estado,version=version+1 WHERE id=:id',array('id'=>(int)$member['id'],'estado'=>$member['estado'])); });
        if (is_file($file['path'])) { unlink($file['path']); }
    }
    $assert(count(glob(Sgpm\Modules\Reportes\ExportRepository::directory().'/*.part'))===0,'Sin archivos parciales abandonados.');
};
