<?php
declare(strict_types=1);
namespace Sgpm\Modules\Proyectos;
use Sgpm\Http\Request;
use Sgpm\Http\Response;

class ExpedienteController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(string $method,int $project,int $id=0): Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $request=new Request($db,$this->requestId); $context=$request->context();
        (new \Sgpm\Auth\ResourceAccess($db))->project($project,'consultar',$context);
        if ($method==='GET') { return Response::json((new ExpedienteRepository($db))->listing($project,$_GET),$this->requestId); }
        $service=new ExpedienteService($db); $data=$request->body();
        if ($method==='DELETE') { $service->delete($project,$id,Request::version($data),$context); return new Response('',204); }
        $result=$service->save($project,$id,$data,$context);
        return $method==='POST'?Response::created($result,$this->requestId,'/api/v1/proyectos/'.$project.'/expedientes/'.$result['id']):Response::json($result,$this->requestId);
    }
}
