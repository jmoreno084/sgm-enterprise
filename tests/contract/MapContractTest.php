<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $p=$f->project(); $q=$f->project($f->b);
    $files=new Sgpm\Modules\Proyectos\ExpedienteService($f->db);
    $own=$files->save($p['id'],0,array('codigo_expediente'=>'T-A','tipo_expediente'=>'TITULO','filiacion'=>'PROPIO'),$f->admin);
    $neighbor=(new Sgpm\Modules\Vecinas\ProjectNeighborService($f->db))->save($p['id'],0,array('nueva_identidad'=>array('nombre'=>'Competencia Ñ')),$f->admin);
    $files->save($p['id'],0,array('codigo_expediente'=>'S-A','tipo_expediente'=>'SOLICITUD','filiacion'=>'VECINO','empresa_vecina_id'=>$neighbor['empresa_vecina_id']),$f->admin);
    $map=new Sgpm\Modules\Visor\MapRepository($f->db); $result=$map->features($p['id'],array(),$f->geo);
    $assert($result['type']==='FeatureCollection' && count($result['features'])===2,'Mapa autorizado completo.');
    $first=$result['features'][0]; $second=$result['features'][1];
    $assert($first['properties']['color_hex']==='#007bff' && strpos($first['properties']['tooltip'],'Proyecto: ')===0,'Propio azul/tooltip local.');
    $assert($second['properties']['color_hex']===$neighbor['color_hex'] && $second['properties']['tooltip']==='Competencia Ñ','Vecino hereda estilo/nombre.');
    $assert($first['geometry']['type']==='MultiPolygon' && !isset($first['properties']['area_ha']),'El mapa conserva geometria y evita calcular areas fuera de Identify.');
    $withArea=$map->features($p['id'],array('page_size'=>'1'),$f->geo,true);
    $assert($withArea['features'][0]['properties']['area_origen']==='GEODESICA' && $withArea['features'][0]['properties']['area_ha']>0,'Area geodesica disponible al solicitar detalle.');
    $detail=(new Sgpm\Modules\Visor\IdentifyService($f->db))->find($p['id'],$own['id'],$f->geo);
    $assert(count($detail['atributos'])===30 && $detail['fuente_disponible'],'Identify 30 atributos.');
    $f->expect(400,function () use ($map,$p,$f) { $map->features($p['id'],array('bbox'=>'-75,6,-74,5'),$f->geo); });
    $f->expect(404,function () use ($map,$q,$f) { $map->features($q['id'],array(),$f->geo); });
    $page=$map->features($p['id'],array('page_size'=>'1'),$f->geo); $assert($page['meta']['next_page']===2 && $page['meta']['total']===2,'Continuacion explicita.');
    $f->expect(400,function () use ($map,$p,$f) { $map->features($p['id'],array('page_size'=>'1001'),$f->geo); });
};
