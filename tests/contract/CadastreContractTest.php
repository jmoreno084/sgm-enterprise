<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $repository=new Sgpm\Modules\Catastro\CadastreRepository($f->db);
    $schema=new Sgpm\Modules\Catastro\CadastreSchema($f->db);
    foreach (array('TITULO','SOLICITUD') as $type) {
        $assert(count($schema->columns($type))===30,'Fuente de 30 columnas verificada.');
        $list=$repository->listing(array('tipo'=>$type)); $assert($list['total']>=1,'Fuente consultable.');
        $code=$type==='TITULO'?'T-A':'S-A'; $detail=$repository->find($type,$code);
        $assert(count($detail['atributos'])===30 && $detail['atributos']['the_geom']['type']==='MultiPolygon','Ficha 30/GeoJSON.');
        foreach (array('codigo'=>$code,'titular'=>'Titular','municipio'=>'Bogota','departamento'=>'Cundinamarca','mineral'=>'Oro') as $filter=>$value) {
            $assert($repository->listing(array('tipo'=>$type,$filter=>$value))['total']>=1,'Filtro '.$filter.' funciona.');
        }
        $assert($detail['atributos']['FECHA_DE_SOLICITUD']===null,'NULL de fecha conservado.');
    }
    $assert($repository->listing(array('codigo'=>"' OR 1=1 --"))['total']===0,'Filtro preparado no ejecuta SQL.');
    $assert($repository->listing(array('codigo'=>'NO-EXISTE'))['items']===array(),'Vacio explicito.');
    $beyond=$repository->listing(array('page'=>'1000000'));
    $assert($beyond['items']===array() && $beyond['total']>=2,'Página vacía conserva total exacto.');
    $f->expect(400,function () use ($repository) { $repository->listing(array('tipo'=>'OTRA')); });
    $f->expect(400,function () use ($repository) { $repository->listing(array('page_size'=>'101')); });
    $f->expect(404,function () use ($repository) { $repository->find('TITULO','NO-EXISTE'); });
};
