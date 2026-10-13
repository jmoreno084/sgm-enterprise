<?php
declare(strict_types=1);
namespace Sgpm\Modules\Usuarios;

class UserController
{
    private $db;
    private $service;
    private $request;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId)
    {
        $this->db=new \Sgpm\Infrastructure\Database($config);
        $this->service=new UserService($this->db,new \Sgpm\Jobs\NotificationRepository($this->db,new \Sgpm\Infrastructure\PayloadCipher($config)));
        $this->request=new \Sgpm\Http\Request($this->db,$requestId); $this->requestId=$requestId;
    }
    public function handle(string $method,int $id=0): \Sgpm\Http\Response
    {
        $context=$this->request->context(); $this->service->requireAdmin($context);
        $repository=new UserRepository($this->db);
        if ($method==='GET') { return \Sgpm\Http\Response::json($id?$repository->find($id):$repository->listing($_GET),$this->requestId); }
        $data=$this->request->body();
        if ($method==='DELETE') { $this->service->delete($id,\Sgpm\Http\Request::version($data),$context); return new \Sgpm\Http\Response('',204); }
        $result=$method==='POST'?$this->service->create($data,$context):$this->service->update($id,$data,$context);
        $response=\Sgpm\Http\Response::json($result,$this->requestId,$method==='POST'?201:200);
        if ($method==='POST') { return new \Sgpm\Http\Response($response->body(),201,array('Content-Type'=>'application/json; charset=utf-8','Cache-Control'=>'no-store','Location'=>'/api/v1/usuarios/'.$result['id'])); }
        return $response;
    }
}
