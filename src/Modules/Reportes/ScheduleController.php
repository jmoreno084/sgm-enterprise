<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class ScheduleController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(string $method,int $id): \Sgpm\Http\Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $request=new \Sgpm\Http\Request($db,$this->requestId); $context=$request->context(); $service=new ScheduleService($db);
        if ($method==='GET') { return \Sgpm\Http\Response::json($service->listing($id,$context),$this->requestId); }
        $body=$request->body();
        if ($method==='DELETE') { $service->delete($id,\Sgpm\Http\Request::version($body),$context); return new \Sgpm\Http\Response('',204); }
        $row=$service->save($method==='POST'?$id:0,$method==='PUT'?$id:0,$body,$context);
        if ($method==='POST') { return \Sgpm\Http\Response::created($row,$this->requestId,'/api/v1/programaciones/'.$row['id']); }
        return \Sgpm\Http\Response::json($row,$this->requestId);
    }
}
