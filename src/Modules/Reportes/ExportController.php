<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class ExportController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(string $method,int $id,bool $download=false): \Sgpm\Http\Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $request=new \Sgpm\Http\Request($db,$this->requestId); $context=$request->context(); $repo=new ExportRepository($db);
        if ($method==='POST') { return \Sgpm\Http\Response::json($repo->create($id,$request->body(),$context),$this->requestId,202); }
        if (!$download) { return \Sgpm\Http\Response::json($repo->find($id,$context),$this->requestId); }
        $file=$repo->download($id,$context);
        $types=array('CSV'=>array('text/csv; charset=utf-8','csv'),'PDF'=>array('application/pdf','pdf'),'XLSX'=>array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','xlsx'),'GEOJSON'=>array('application/geo+json','geojson'),'KML'=>array('application/vnd.google-earth.kml+xml','kml'),'SHAPEFILE'=>array('application/zip','zip'));
        $type=$types[$file['formato']]; return new \Sgpm\Http\FileResponse($file['path'],$type[0],'sgpm-exportacion-'.$id.'.'.$type[1]);
    }
}
