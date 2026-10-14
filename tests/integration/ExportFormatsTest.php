<?php
declare(strict_types=1);
return function ($assert) {
    $tmp=new Sgpm\Infrastructure\TemporaryFiles();
    $row=array_fill_keys(Sgpm\Modules\Reportes\ExportTable::columns(),null);
    $row=array_merge($row,array('CODIGO_EXPEDIENTE'=>'EXP-Ñ','SOLICITANTES_O_TITULARES'=>'=José '.str_repeat('Á',200),'AREA_HA'=>12.5,'FECHA_DE_SOLICITUD'=>'2026-10-13','proyecto_id'=>1,'proyecto_nombre'=>'Minería & <Oro>','tipo_expediente'=>'TITULO','filiacion'=>'PROPIO','fuente_disponible'=>true,'the_geom'=>array('type'=>'MultiPolygon','coordinates'=>array(array(array(array(-74.1,4.6),array(-74.09,4.6),array(-74.09,4.61),array(-74.1,4.6)))))));
    $missing=$row; $missing['CODIGO_EXPEDIENTE']='AUSENTE'; $missing['the_geom']=null; $missing['fuente_disponible']=false;
    $job=array('empresa_id'=>1,'empresa_nombre'=>'Empresa Ñ','proyecto_id'=>1,'filtros'=>'{}');
    try {
        foreach (array('Csv'=>'csv','Xlsx'=>'xlsx','Pdf'=>'pdf','GeoJson'=>'geojson','Kml'=>'kml','Shapefile'=>'zip') as $name=>$ext) {
            $class='Sgpm\\Modules\\Reportes\\'.$name.'Exporter'; $path=$tmp->path('report.'.$ext);
            (new $class())->write(new ArrayIterator(array($row,$missing)),$path,$job);
            $assert(is_file($path) && filesize($path)>0,'Generación '.$name);
        }
        $csv=fopen($tmp->path('report.csv'),'rb'); $header=fgetcsv($csv); $first=fgetcsv($csv); $second=fgetcsv($csv); fclose($csv);
        $assert($first[array_search('SOLICITANTES_O_TITULARES',$header,true)]==="'".$row['SOLICITANTES_O_TITULARES'] && $second[0]==='AUSENTE','CSV neutraliza fórmula sin perder texto ni fuente ausente.');
        $geo=json_decode(file_get_contents($tmp->path('report.geojson')),true);
        $assert(count($geo['features'])===1 && count($geo['manifest']['omisiones'])===1 && $geo['features'][0]['geometry']===$row['the_geom'],'GeoJSON original y omisiones explícitas.');
        $dom=new DOMDocument(); $assert($dom->load($tmp->path('report.kml'),LIBXML_NONET),'KML XML válido.');
        $xp=new DOMXPath($dom); $xp->registerNamespace('k','http://www.opengis.net/kml/2.2');
        $assert($xp->query('//k:Polygon')->length===1 && $xp->evaluate('string(//k:altitudeMode)')==='clampToGround','KML sin Z ficticia.');
        $pdf=file_get_contents($tmp->path('report.pdf')); $assert(substr($pdf,0,5)==='%PDF-' && strpos($pdf,'/FontFile2')!==false,'PDF real con fuente UTF8 embebida.');
        $zip=new ZipArchive(); $zip->open($tmp->path('report.xlsx')); $xml=$zip->getFromName('xl/worksheets/sheet1.xml'); $dom->loadXML($xml,LIBXML_NONET);
        $assert(strpos($xml,'inlineStr')!==false && strpos($xml,'<f>')===false && strpos($xml,'=José')!==false && $zip->getFromName('[Content_Types].xml')!==false,'OOXML real con texto explícito sin fórmulas.'); $zip->close();
        $zip=new ZipArchive(); $zip->open($tmp->path('report.zip')); $fields=json_decode($zip->getFromName('fields.json'),true); $side=json_decode($zip->getFromName('attributes.json'),true);
        $assert($fields['CODIGO_EXPEDIENTE']==='EXP_CODE' && $fields['SOLICITANTES_O_TITULARES']==='TITULARES' && $side[0]['campos']['SOLICITANTES_O_TITULARES']===$row['SOLICITANTES_O_TITULARES'],'DBF reversible con texto íntegro en sidecar.');
        foreach (array('shp','shx','dbf','prj','cpg') as $ext) { file_put_contents($tmp->path('expedientes.'.$ext),$zip->getFromName('expedientes.'.$ext)); } $zip->close();
        $reader=new Shapefile\ShapefileReader($tmp->path('expedientes.shp'),array(Shapefile\Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES=>true));
        $shape=$reader->fetchRecord(); $assert($shape->getData('EXP_CODE')==='EXP-Ñ' && !$reader->fetchRecord(),'Shapefile legible sin inventar fuente ausente.'); $reader=null;
    } finally { $tmp->close(); }
};
