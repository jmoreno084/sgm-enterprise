<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/bootstrap.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
$config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php'); $config->assertTestDatabase();
$row=array_fill_keys(Sgpm\Modules\Reportes\ExportTable::columns(),null);
$row=array_merge($row,array('CODIGO_EXPEDIENTE'=>'UI-Ñ','proyecto_id'=>1,'fuente_disponible'=>true,'the_geom'=>array('type'=>'MultiPolygon','coordinates'=>array(array(array(array(-74.1,4.6),array(-74.09,4.6),array(-74.09,4.61),array(-74.1,4.6)))))));
$path='/tmp/sgpm-browser-shape.zip'; if (is_file($path)) { unlink($path); }
(new Sgpm\Modules\Reportes\ShapefileExporter())->write(new ArrayIterator(array($row)),$path,array('empresa_id'=>1,'proyecto_id'=>1,'filtros'=>'{}'));
