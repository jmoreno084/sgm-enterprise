<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class GeoJsonExporter
{
    public function write(\Traversable $rows,string $path,array $job)
    {
        $h=fopen($path,'xb'); if (!$h) { throw new \RuntimeException('Archivo no disponible.'); }
        $missing=array(); $count=0;
        try {
            fwrite($h,'{"type":"FeatureCollection","features":[');
            foreach ($rows as $row) {
                if ($row['the_geom']===null) { $missing[]=array('codigo'=>$row['CODIGO_EXPEDIENTE'],'proyecto_id'=>$row['proyecto_id'],'tipo'=>$row['tipo_expediente']); continue; }
                $geometry=$row['the_geom']; unset($row['the_geom']);
                $json=json_encode(array('type'=>'Feature','geometry'=>$geometry,'properties'=>$row),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                if ($json===false || fwrite($h,($count?',':'').$json)===false) { throw new \RuntimeException('Escritura incompleta.'); } ++$count;
            }
            fwrite($h,'],"manifest":'.json_encode(ExportTable::manifest($job,$missing,$count),JSON_UNESCAPED_UNICODE).'}');
        } finally { fclose($h); }
    }
}
