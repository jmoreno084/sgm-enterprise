<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class ShapefileExporter
{
    public function write(\Traversable $rows,string $path,array $job)
    {
        \Sgpm\Infrastructure\Vendor::shapefile(); $tmp=new \Sgpm\Infrastructure\TemporaryFiles(); $writer=null; $zip=null;
        try {
            $writer=new \Shapefile\ShapefileWriter($tmp->path('expedientes.shp'));
            $writer->setShapeType(\Shapefile\Shapefile::SHAPE_TYPE_POLYGON); $writer->setCharset('UTF-8');
            $writer->setPRJ('GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433],AUTHORITY["EPSG","4326"]]');
            $mapping=array();
            foreach (ExportTable::columns() as $index=>$field) {
                if ($field==='the_geom') { continue; }
                $alias=$field==='CODIGO_EXPEDIENTE'?'EXP_CODE':($field==='SOLICITANTES_O_TITULARES'?'TITULARES':'F'.str_pad((string)($index+1),2,'0',STR_PAD_LEFT));
                $mapping[$field]=$alias; $writer->addCharField($alias,254);
            }
            $side=fopen($tmp->path('attributes.json'),'xb'); fwrite($side,'['); $sideCount=0; $count=0; $missing=array();
            try {
                foreach ($rows as $row) {
                    if ($row['the_geom']===null) { $missing[]=array('codigo'=>$row['CODIGO_EXPEDIENTE'],'proyecto_id'=>$row['proyecto_id'],'tipo'=>$row['tipo_expediente']); continue; }
                    $geometry=$row['the_geom'];
                    if ($geometry['type']==='Polygon') { $geometry=array('type'=>'MultiPolygon','coordinates'=>array($geometry['coordinates'])); }
                    if ($geometry['type']!=='MultiPolygon') { throw new \RuntimeException('Geometría oficial no exportable.'); }
                    $shape=new \Shapefile\Geometry\MultiPolygon(); $shape->initFromGeoJSON(json_encode($geometry)); $overflow=array();
                    foreach ($mapping as $field=>$alias) {
                        $value=ExportTable::text($row[$field]);
                        if (strlen($value)>254) { $overflow[$field]=$value; $value='[attributes.json registro '.($count+1).']'; }
                        $shape->setData($alias,$value);
                    }
                    if ($overflow) {
                        fwrite($side,($sideCount?',':'').json_encode(array('EXP_CODE'=>$row['CODIGO_EXPEDIENTE'],'proyecto_id'=>$row['proyecto_id'],'tipo'=>$row['tipo_expediente'],'registro'=>$count+1,'campos'=>$overflow),JSON_UNESCAPED_UNICODE)); ++$sideCount;
                    }
                    $writer->writeRecord($shape); ++$count;
                }
                fwrite($side,']');
            } finally { fclose($side); }
            $writer=null;
            $manifest=ExportTable::manifest($job,$missing,$count); $manifest['avisos']=array('DBF: atributos expresados como texto UTF-8; vacíos representan NULL. Valores >254 bytes íntegros en attributes.json.'); $manifest['registros_sidecar']=$sideCount;
            $zip=new \ZipArchive(); if ($zip->open($path,\ZipArchive::CREATE|\ZipArchive::EXCL)!==true) { throw new \RuntimeException('Archivo no disponible.'); }
            foreach (array('shp','shx','dbf','prj','cpg') as $ext) { $zip->addFile($tmp->path('expedientes.'.$ext),'expedientes.'.$ext); }
            $zip->addFromString('manifest.json',json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            $zip->addFromString('fields.json',json_encode($mapping,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); $zip->addFile($tmp->path('attributes.json'),'attributes.json');
            if (!$zip->close()) { throw new \RuntimeException('Escritura incompleta.'); } $zip=null;
        } finally { $writer=null; if ($zip!==null) { $zip->close(); } $tmp->close(); }
    }
}
