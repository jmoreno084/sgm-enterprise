<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $p=$f->project();
    (new Sgpm\Modules\Proyectos\ExpedienteService($f->db))->save($p['id'],0,array('codigo_expediente'=>'T-A','tipo_expediente'=>'TITULO','filiacion'=>'PROPIO'),$f->admin);
    $repo=new Sgpm\Modules\Reportes\ExportRepository($f->db);
    $f->expect(403,function () use ($repo,$f) { $repo->create($f->a,array('formato'=>'CSV'),$f->geo); });
    $f->expect(404,function () use ($repo,$f) { $repo->create($f->b,array('formato'=>'CSV'),$f->manager); });
    $f->expect(400,function () use ($repo,$f) { $repo->create($f->a,array('formato'=>'HTML'),$f->manager); });
    $f->expect(400,function () use ($repo,$f) { $repo->create($f->a,array('formato'=>'CSV','filtros'=>array('sql'=>'1=1')),$f->manager); });
    $job=$repo->create($f->a,array('formato'=>'CSV','proyecto_id'=>$p['id']),$f->manager);
    $assert($job['estado']==='PENDIENTE' && !isset($job['archivo_relativo']),'Job privado sin ruta interna.');
    $assert($repo->find($job['id'],$f->admin)['id']===$job['id'],'Administrador autorizado.');
    $f->expect(404,function () use ($repo,$job,$f) { $repo->find($job['id'],$f->geo); });
    $rows=iterator_to_array($repo->records($repo->internal($job['id']),$f->manager));
    $assert(count($rows)===1 && $rows[0]['CODIGO_EXPEDIENTE']==='T-A' && $rows[0]['the_geom']['type']==='MultiPolygon','Conjunto y geometria originales.');
    $f->expect(409,function () use ($repo,$job,$f) { $repo->download($job['id'],$f->manager); });
    $f->db->execute("UPDATE sgpm.exportacion_job SET expira_en=timezone('UTC',now())-interval '1 second' WHERE id=:id",array('id'=>$job['id']));
    $f->expect(410,function () use ($repo,$job,$f) { $repo->download($job['id'],$f->manager); });
};
