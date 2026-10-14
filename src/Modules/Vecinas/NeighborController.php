<?php
declare(strict_types=1);
namespace Sgpm\Modules\Vecinas;
use Sgpm\Http\Response;
use Sgpm\Http\Request;

class NeighborController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(string $method,int $id=0): Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $request=new Request($db,$this->requestId); $context=$request->context();
        (new \Sgpm\Auth\ResourceAccess($db))->admin($context); $repository=new NeighborRepository($db);
        if ($method==='GET') { return Response::json($id?$repository->find($id):$repository->listing($_GET),$this->requestId); }
        $service=new NeighborService($db); $data=$request->body();
        if ($method==='DELETE') { $service->delete($id,Request::version($data),$context); return new Response('',204); }
        return Response::json($service->save($id,$data,$context),$this->requestId);
    }
}
