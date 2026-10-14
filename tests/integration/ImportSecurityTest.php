<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $service=new Sgpm\Modules\Visor\ImportService(); $tmp=new Sgpm\Infrastructure\TemporaryFiles();
    $before=glob(sys_get_temp_dir().'/sgpm-work-*'); $dbBefore=(int)$f->db->execute('SELECT count(*) FROM sgpm.auditoria')->fetchColumn();
    try {
        $path=$tmp->path('test.geojson'); $feature=array('type'=>'Feature','geometry'=>array('type'=>'Point','coordinates'=>array(-74.1,4.6)),'properties'=>array('nombre'=>'José <script>'));
        file_put_contents($path,json_encode($feature)); $result=$service->read($path,'test.geojson');
        $assert(count($result['features'])===1 && $result['crs']==='EPSG:4326','GeoJSON de trabajo valido.');
        file_put_contents($path,'{corrupto'); $f->expect(400,function () use ($service,$path) { $service->read($path,'test.geojson'); });
        file_put_contents($path,json_encode(array('type'=>'FeatureCollection','features'=>array_fill(0,5001,$feature))));
        $f->expect(413,function () use ($service,$path) { $service->read($path,'test.geojson'); });
        $bad=$feature; $bad['geometry']['coordinates']=array(999,4); file_put_contents($path,json_encode($bad));
        $f->expect(400,function () use ($service,$path) { $service->read($path,'test.geojson'); });
        $bad=$feature; $bad['crs']=array('type'=>'name','properties'=>array('name'=>'EPSG:3857')); file_put_contents($path,json_encode($bad));
        $f->expect(400,function () use ($service,$path) { $service->read($path,'test.geojson'); });
        file_put_contents($path,'<?xml version="1.0"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document><Placemark><name>Ñ</name><Point><coordinates>-74.1,4.6</coordinates></Point></Placemark></Document></kml>');
        $assert(count($service->read($path,'test.kml')['features'])===1,'KML2.2 valido.');
        file_put_contents($path,'<!DOCTYPE kml [<!ENTITY leak SYSTEM "file:///etc/passwd">]><kml xmlns="http://www.opengis.net/kml/2.2">&leak;</kml>');
        $f->expect(400,function () use ($service,$path) { $service->read($path,'test.kml'); });
        $zipPath=$tmp->path('test.zip'); $zip=new ZipArchive(); $zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE); $zip->addFromString('../escape.shp','bad'); $zip->close();
        $f->expect(400,function () use ($service,$zipPath) { $service->read($zipPath,'test.zip'); });
        $zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE); $zip->addFromString('test.shp','bad'); $zip->addFromString('run.php','<?php'); $zip->close();
        $f->expect(400,function () use ($service,$zipPath) { $service->read($zipPath,'test.zip'); });
        $zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE); $zip->addFromString('test.shp',str_repeat('X',101*1024*1024)); $zip->close();
        $f->expect(413,function () use ($service,$zipPath) { $service->read($zipPath,'test.zip'); });
        $pointCount=500001; $contentBytes=44+$pointCount*16; $shapeBytes=str_repeat("\0",108+$contentBytes);
        $shapeBytes=substr_replace($shapeBytes,pack('N',9994),0,4);
        $shapeBytes=substr_replace($shapeBytes,pack('N',strlen($shapeBytes)/2),24,4);
        $shapeBytes=substr_replace($shapeBytes,pack('V',1000),28,4); $shapeBytes=substr_replace($shapeBytes,pack('V',5),32,4);
        $shapeBytes=substr_replace($shapeBytes,pack('N',1),100,4); $shapeBytes=substr_replace($shapeBytes,pack('N',$contentBytes/2),104,4);
        $shapeBytes=substr_replace($shapeBytes,pack('V',5),108,4); $shapeBytes=substr_replace($shapeBytes,pack('V',1),144,4);
        $shapeBytes=substr_replace($shapeBytes,pack('V',$pointCount),148,4);
        $validPrj='GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433],AUTHORITY["EPSG","4326"]]';
        $entries=array('test.shp'=>$shapeBytes,'test.shx'=>'','test.dbf'=>'','test.prj'=>$validPrj,'test.cpg'=>'UTF-8');
        $writeZip=function ($path,array $files,bool $symlink=false) {
            $archive=new ZipArchive(); $archive->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
            foreach ($files as $name=>$bytes) { $archive->addFromString($name,$bytes); }
            if ($symlink) { $archive->addFromString('link.shp','link'); $archive->setExternalAttributesName('link.shp',ZipArchive::OPSYS_UNIX,0120000<<16); }
            $archive->close();
        };
        $writeZip($zipPath,$entries);
        $f->expect(413,function () use ($service,$zipPath) { $service->read($zipPath,'test.zip'); });
        unset($entries['test.prj']); $writeZip($zipPath,$entries);
        $f->expect(400,function () use ($service,$zipPath) { $service->read($zipPath,'test.zip'); });
        $entries['test.prj']='PROJCS["WGS 84 / Pseudo-Mercator"]'; $writeZip($zipPath,$entries);
        $f->expect(400,function () use ($service,$zipPath) { $service->read($zipPath,'test.zip'); });
        $entries['test.prj']=$validPrj; $entries['test.cpg']='ISO-8859-1'; $writeZip($zipPath,$entries);
        $f->expect(400,function () use ($service,$zipPath) { $service->read($zipPath,'test.zip'); });
        $entries['test.cpg']='UTF-8'; $writeZip($zipPath,$entries,true);
        $f->expect(400,function () use ($service,$zipPath) { $service->read($zipPath,'test.zip'); });
        $large=$tmp->path('large.geojson'); $handle=fopen($large,'wb'); ftruncate($handle,20*1024*1024+1); fclose($handle);
        $f->expect(413,function () use ($service,$large) { $service->read($large,'large.geojson'); });
        $f->expect(415,function () use ($service,$path) { $service->read($path,'test.exe'); });
        $assert((int)$f->db->execute('SELECT count(*) FROM sgpm.auditoria')->fetchColumn()===$dbBefore,'Importar no escribe maestros/catastro.');
        $after=glob(sys_get_temp_dir().'/sgpm-work-*'); sort($before); sort($after); $assert($before===$after,'Temporales importacion limpiados tras error/exito.');
    } finally { $tmp->close(); }
};
