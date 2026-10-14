<?php
declare(strict_types=1);
return function ($assert) {
    \Sgpm\Infrastructure\Vendor::pdf();
    $pdf=new tFPDF(); $pdf->AddPage(); $pdf->AddFont('DejaVu','','DejaVuSans.ttf',true); $pdf->SetFont('DejaVu','',12); $pdf->MultiCell(180,6,'José Muñoz — Minería Bogotá');
    $document=$pdf->Output('S'); $assert(substr($document,0,5)==='%PDF-' && strpos($document,'/FontFile2')!==false,'tFPDF UTF8 con fuente embebida sobre PHP7.0.33.');
    \Sgpm\Infrastructure\Vendor::shapefile(); $tmp=new \Sgpm\Infrastructure\TemporaryFiles();
    try {
        $path=$tmp->path('test.shp'); $writer=new \Shapefile\ShapefileWriter($path);
        $writer->setShapeType(\Shapefile\Shapefile::SHAPE_TYPE_POLYGON);
        $writer->setCharset('UTF-8');
        $writer->setPRJ('GEOGCS["WGS 84",DATUM["WGS_1984",SPHEROID["WGS 84",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433],AUTHORITY["EPSG","4326"]]');
        $writer->addCharField('EXP_CODE',100);
        $shape=new \Shapefile\Geometry\MultiPolygon();
        $shape->initFromGeoJSON(json_encode(array('type'=>'MultiPolygon','coordinates'=>array(array(array(array(-74.1,4.6),array(-74.09,4.6),array(-74.09,4.61),array(-74.1,4.6)))))));
        $shape->setData('EXP_CODE','PRUEBA-Ñ'); $writer->writeRecord($shape); $writer=null;
        $reader=new \Shapefile\ShapefileReader($path,array(\Shapefile\Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES=>true));
        $record=$reader->fetchRecord(); $assert($record && $record->getData('EXP_CODE')==='PRUEBA-Ñ','Reader/Writer Shapefile sobre PHP7.0.33.'); $reader=null;
        $archive=$tmp->path('test.zip'); $zip=new ZipArchive(); $zip->open($archive,ZipArchive::CREATE);
        foreach (array('shp','shx','dbf','prj','cpg') as $extension) { $zip->addFile($tmp->path('test.'.$extension),'test.'.$extension); } $zip->close();
        $imported=(new \Sgpm\Modules\Visor\ImportService())->read($archive,'test.zip');
        $assert(count($imported['features'])===1 && $imported['features'][0]['properties']->EXP_CODE==='PRUEBA-Ñ','Importacion ZIP Shapefile valida conserva UTF8.');
    } finally { $tmp->close(); }
};
