<?php
declare(strict_types=1);
namespace Sgpm\Modules\Proyectos;
use Sgpm\Http\Request;
use Sgpm\Http\Response;

class ProjectController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(string $method,int $company=0,int $id=0): Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $request=new Request($db,$this->requestId); $context=$request->context();
        $service=new ProjectService($db);
        if ($id) { $row=$service->find($id,$context); $company=$row['empresa_id']; }
        if ($method==='GET') {
            if ($id) { return Response::json($row,$this->requestId); }
            (new \Sgpm\Auth\ResourceAccess($db))->company($company,'consultar',$context);
            return Response::json((new ProjectRepository($db))->listing($company,$_GET),$this->requestId);
        }
        $data=$request->body();
        if ($method==='DELETE') { $service->delete($id,Request::version($data),$context); return new Response('',204); }
        $result=$service->save($company,$id,$data,$context);
        return $method==='POST'?Response::created($result,$this->requestId,'/api/v1/proyectos/'.$result['id']):Response::json($result,$this->requestId);
    }
}
