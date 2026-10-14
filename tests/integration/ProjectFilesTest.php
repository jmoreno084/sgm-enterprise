<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $p=$f->project(); $other=$f->project();
    $files=new Sgpm\Modules\Proyectos\ExpedienteService($f->db);
    $source=$f->db->execute('SELECT codigo_expediente FROM public.geo_titulos_mineros ORDER BY codigo_expediente LIMIT 1')->fetchColumn();
    $data=array('codigo_expediente'=>$source,'tipo_expediente'=>'TITULO','filiacion'=>'PROPIO');
    $file=$files->save($p['id'],0,$data,$f->geo);
    $assert($file['codigo_expediente']===$source,'Asocia fuente existente.');
    $centroid=(new Sgpm\Modules\Proyectos\ProjectService($f->db))->find($p['id'],$f->geo)['centroide'];
    $assert($centroid['type']==='Point','Centroide PostGIS persistido.');
    $f->expect(409,function () use ($files,$p,$data,$f) { $files->save($p['id'],0,$data,$f->admin); });
    $f->expect(400,function () use ($files,$p,$data,$f) { $data['codigo_expediente']='NO-EXISTE'; $files->save($p['id'],0,$data,$f->admin); });
    $f->expect(400,function () use ($files,$p,$data,$f) { $data['tipo_expediente']='SOLICITUD'; $files->save($p['id'],0,$data,$f->admin); });
    $files->save($other['id'],0,$data,$f->admin);
    $f->expect(400,function () use ($files,$p,$file,$f) { $files->save($p['id'],$file['id'],array('version'=>1,'codigo_expediente'=>'CAMBIO'),$f->admin); });
    $f->expect(400,function () use ($files,$p,$file,$f) { $files->save($p['id'],$file['id'],array('version'=>1,'filiacion'=>'VECINO','empresa_vecina_id'=>999999),$f->admin); });
    $f->expect(409,function () use ($p,$f) { (new Sgpm\Modules\Proyectos\ProjectService($f->db))->delete($p['id'],1,$f->admin); });
    $files->delete($p['id'],$file['id'],1,$f->admin);
    $assert((new Sgpm\Modules\Proyectos\ProjectService($f->db))->find($p['id'],$f->admin)['centroide']===null,'Sin fuentes propias, centroide NULL.');
};
