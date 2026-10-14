<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $repository=new Sgpm\Modules\Auditoria\AuditRepository($f->db);
    $p=$f->project(); $q=$f->project($f->b);
    $a=$repository->listing($f->a,array(),$f->manager); $assert($a['total']>0,'Gerente consulta eventos A.');
    foreach ($a['items'] as $event) { $assert($event['empresa_id_contexto']===$f->a,'No aparecen B/global.'); }
    $f->expect(403,function () use ($repository,$f) { $repository->listing($f->a,array(),$f->geo); });
    $f->expect(403,function () use ($repository,$f) { $repository->listing(0,array(),$f->manager); });
    $f->expect(404,function () use ($repository,$f) { $repository->listing($f->b,array(),$f->manager); });
    $b=$repository->listing($f->b,array(),$f->admin)['items'][0];
    $f->expect(404,function () use ($repository,$f,$b) { $repository->find($f->a,$b['id'],$f->manager); });
    (new Sgpm\Modules\Proyectos\ProjectService($f->db))->delete($p['id'],1,$f->admin);
    $events=$repository->listing($f->a,array('entidad'=>'proyecto','accion'=>'DELETE'),$f->manager)['items'];
    $matches=array_filter($events,function ($event) use ($p) { return $event['entidad_id']===$p['id']; });
    $assert(count($matches)===1,'Contexto historico tras borrar proyecto.');
};
