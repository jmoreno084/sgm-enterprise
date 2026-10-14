<?php
declare(strict_types=1);
namespace Sgpm\Modules\Visor;

class ImportService
{
    public function read(string $path,string $name): array
    {
        if (!is_file($path) || filesize($path)>20*1024*1024) { throw new \Sgpm\Http\HttpException(413,'ARCHIVO_GRANDE','La capa admite hasta 20 MiB.'); }
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if (!in_array($extension,array('geojson','json','kml','zip'),true)) { throw new \Sgpm\Http\HttpException(415,'FORMATO_INVALIDO','Selecciona GeoJSON, KML o ZIP Shapefile.'); }
        if ($extension==='zip') { $collection=$this->zip($path); }
        else { $text=file_get_contents($path); $collection=$extension==='kml'?(new KmlReader())->read($text):(new GeoJsonReader())->read($text); }
        $collection['nombre_capa']=mb_substr(preg_replace('/[\x00-\x1F\x7F]/u','',basename(str_replace('\\','/',$name))),0,255,'UTF-8');
        $collection['avisos']=array('Capa temporal de trabajo; no se guarda en proyectos ni modifica el catastro.'); $collection['crs']='EPSG:4326';
        return $collection;
    }
    private function zip(string $path): array
    {
        $zip=new \ZipArchive(); if ($zip->open($path)!==true) { GeoJsonReader::invalid('El archivo ZIP no es válido.'); }
        $temporary=new \Sgpm\Infrastructure\TemporaryFiles();
        try {
            if ($zip->numFiles>256) { GeoJsonReader::limit(); }
            $size=0; $entries=array(); $names=array(); $shapes=array();
            for ($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i); if ($stat===false) { GeoJsonReader::invalid(); }
                $name=str_replace('\\','/',$stat['name']);
                if (strpos($name,"\0")!==false || preg_match('~(^/|^[A-Za-z]:|(^|/)\.\.(/|$))~',$name)) { GeoJsonReader::invalid('El ZIP contiene rutas no permitidas.'); }
                $opsys=0; $attributes=0;
                if ($zip->getExternalAttributesIndex($i,$opsys,$attributes) && (($attributes>>16)&0170000)===0120000) { GeoJsonReader::invalid('El ZIP no admite enlaces simbólicos.'); }
                if (substr($name,-1)==='/') { continue; }
                $base=strtolower(basename($name)); $extension=pathinfo($base,PATHINFO_EXTENSION);
                if (!in_array($extension,array('shp','shx','dbf','prj','cpg','json'),true) || isset($names[$base]) || !preg_match('/^[A-Za-z0-9_.-]+$/D',$base)) { GeoJsonReader::invalid('El ZIP contiene archivos no admitidos o nombres repetidos.'); }
                $names[$base]=true; $size+=(int)$stat['size'];
                if ($size>100*1024*1024) { throw new \Sgpm\Http\HttpException(413,'ZIP_EXCESIVO','El ZIP supera 100 MiB al expandirse.'); }
                $entries[]=array('original'=>$stat['name'],'name'=>$base,'size'=>(int)$stat['size']);
                if ($extension==='shp') { $shapes[]=$base; }
            }
            if (count($shapes)!==1) { GeoJsonReader::invalid('El ZIP debe contener un único conjunto Shapefile.'); }
            foreach ($entries as $entry) {
                $input=$zip->getStream($entry['original']); if ($input===false) { GeoJsonReader::invalid(); }
                $output=fopen($temporary->path($entry['name']),'xb');
                if ($output===false) { fclose($input); throw new \RuntimeException('Temporal no disponible.'); }
                try { $copied=stream_copy_to_stream($input,$output,$entry['size']+1); }
                finally { fclose($input); fclose($output); }
                if ($copied!==$entry['size']) { GeoJsonReader::invalid('El ZIP está corrupto.'); }
            }
            return (new ShapefileReader())->read($temporary->path($shapes[0]));
        } finally { $zip->close(); $temporary->close(); }
    }
}
