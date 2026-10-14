<?php
declare(strict_types=1);
namespace Sgpm\Modules\Visor;

class ShapefileReader
{
    public function read(string $path): array
    {
        $base=substr($path,0,-4);
        foreach (array('shp','shx','dbf','prj') as $extension) { if (!is_file($base.'.'.$extension)) { GeoJsonReader::invalid('El ZIP requiere .shp, .shx, .dbf y .prj del mismo conjunto.'); } }
        $prj=file_get_contents($base.'.prj');
        if (!preg_match('/^\s*GEOGCS\s*\[/i',$prj) || !preg_match('/WGS[_ ]?(?:1984|84)/i',$prj) || preg_match('/AUTHORITY\s*\[\s*"EPSG"\s*,\s*"(?!4326")[0-9]+"\s*\]\s*\]\s*$/i',$prj)) { GeoJsonReader::invalid('Exporta el Shapefile en WGS84 (EPSG:4326).'); }
        if (is_file($base.'.cpg') && !in_array(strtoupper(trim(file_get_contents($base.'.cpg'))),array('UTF-8','UTF8','65001'),true)) { GeoJsonReader::invalid('El archivo .cpg debe indicar UTF-8.'); }
        $this->preflight($path);
        \Sgpm\Infrastructure\Vendor::shapefile(); $features=array(); $reader=null;
        try {
            $reader=new \Shapefile\ShapefileReader($path,array(\Shapefile\Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES=>true,\Shapefile\Shapefile::OPTION_SUPPRESS_M=>true));
            while ($record=$reader->fetchRecord()) {
                if ($record->isDeleted()) { continue; }
                if (count($features)>=5000) { GeoJsonReader::limit(); }
                $features[]=array('type'=>'Feature','geometry'=>json_decode($record->getGeoJSON(),true),'properties'=>$record->getDataArray());
            }
            return (new GeoJsonReader())->normalize(array('type'=>'FeatureCollection','features'=>$features));
        } catch (\Shapefile\ShapefileException $error) { GeoJsonReader::invalid('El conjunto Shapefile está incompleto o no puede leerse.'); }
        finally { $reader=null; }
    }
    private function preflight(string $path)
    {
        $stream=fopen($path,'rb'); if ($stream===false) { GeoJsonReader::invalid(); }
        try {
            $size=filesize($path); $header=fread($stream,100);
            if (strlen($header)!==100) { GeoJsonReader::invalid(); }
            $values=unpack('Ncode/x20/Nlength/Vversion/Vtype',$header);
            if ($values['code']!==9994 || $values['version']!==1000 || $values['length']*2!==$size) { GeoJsonReader::invalid(); }
            $objects=0; $vertices=0;
            while (ftell($stream)<$size) {
                $record=fread($stream,8); if (strlen($record)!==8) { GeoJsonReader::invalid(); }
                $info=unpack('Nnumber/Nlength',$record); $length=$info['length']*2; $start=ftell($stream);
                if ($length<4 || $start+$length>$size) { GeoJsonReader::invalid(); }
                $prefix=fread($stream,min(44,$length)); $type=unpack('Vtype',substr($prefix,0,4))['type'];
                if (++$objects>5000) { GeoJsonReader::limit(); }
                if (in_array($type,array(1,11,21),true)) { $vertices++; }
                elseif (in_array($type,array(3,5,13,15,23,25,8,18,28),true)) {
                    $offset=in_array($type,array(8,18,28),true)?36:40;
                    if (strlen($prefix)<$offset+4) { GeoJsonReader::invalid(); }
                    $count=unpack('Vcount',substr($prefix,$offset,4))['count']; $vertices+=$count;
                    if ($vertices>500000) { GeoJsonReader::limit(); }
                    if ($length<$offset+4+$count*16) { GeoJsonReader::invalid(); }
                } elseif ($type!==0) { GeoJsonReader::invalid('El tipo de geometría Shapefile no está admitido.'); }
                if ($vertices>500000) { GeoJsonReader::limit(); }
                if (fseek($stream,$start+$length)!==0) { GeoJsonReader::invalid(); }
            }
        } finally { fclose($stream); }
    }
}
