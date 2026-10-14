<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $a=$f->project(); $b=$f->project($f->b);
    $service=new Sgpm\Modules\Vecinas\ProjectNeighborService($f->db);
    $nit='NEIGHBOR-'.bin2hex(random_bytes(5));
    $created=$service->save($a['id'],0,array('nueva_identidad'=>array('nombre'=>'Vecina Ñ','nit'=>$nit),'observaciones'=>'Notas A'),$f->geo);
    $assert($created['orden_registro']===1 && $created['color_hex']==='#8a2be2' && $created['patron_indice']===0,'Estilo de primer vinculo.');
    $identity=$created['empresa_vecina_id'];
    $shared=$service->save($b['id'],0,array('empresa_vecina_id'=>$identity,'observaciones'=>'Notas B'),$f->admin);
    $assert($shared['empresa_vecina_id']===$identity,'Identidad reutilizada cross-empresa.');
    $resolved=$service->resolve($a['id'],$nit,$f->geo);
    $assert(array_keys($resolved)===array('id','nombre','nit'),'Resolver minimo sin asociaciones privadas.');
    $f->expect(409,function () use ($service,$a,$identity,$f) { $service->save($a['id'],0,array('empresa_vecina_id'=>$identity),$f->geo); });
    $f->expect(409,function () use ($service,$a,$nit,$f) { $service->save($a['id'],0,array('nueva_identidad'=>array('nombre'=>'Duplicada','nit'=>$nit)),$f->geo); });
    $f->expect(400,function () use ($service,$a,$identity,$f) { $service->save($a['id'],0,array('empresa_vecina_id'=>$identity,'nueva_identidad'=>array('nombre'=>'No')),$f->geo); });
    $service->save($a['id'],$identity,array('version'=>1,'observaciones'=>'Editada A'),$f->geo);
    $assert((new Sgpm\Modules\Vecinas\ProjectNeighborRepository($f->db))->find($b['id'],$identity)['observaciones']==='Notas B','Notas A no alteran B.');
    $f->expect(403,function () use ($identity,$f) { (new Sgpm\Modules\Vecinas\NeighborService($f->db))->save($identity,array('version'=>1,'nombre'=>'No'),$f->geo); });
    $f->expect(409,function () use ($identity,$f) { (new Sgpm\Modules\Vecinas\NeighborService($f->db))->delete($identity,1,$f->admin); });
    $f->expect(404,function () use ($service,$b,$identity,$f) { $service->save($b['id'],$identity,array('version'=>1,'observaciones'=>'No'),$f->geo); });
    for ($n=2;$n<=17;$n++) { $service->save($a['id'],0,array('nueva_identidad'=>array('nombre'=>'Vecina '.$n)),$f->geo); }
    $items=(new Sgpm\Modules\Vecinas\ProjectNeighborRepository($f->db))->listing($a['id']);
    $assert(count(array_unique(array_column($items,'color_hex')))===17,'17 colores distintos.');
    $assert($items[8]['patron_indice']===1 && $items[16]['patron_indice']===2,'Patrones desde9 y17.');
};
