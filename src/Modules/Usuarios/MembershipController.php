<?php
declare(strict_types=1);
namespace Sgpm\Modules\Usuarios;

class MembershipController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(string $method,int $company,int $id=0): \Sgpm\Http\Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config);
        $users=new UserService($db,new \Sgpm\Jobs\NotificationRepository($db,new \Sgpm\Infrastructure\PayloadCipher($this->config)));
        $request=new \Sgpm\Http\Request($db,$this->requestId); $context=$request->context(); $users->requireAdmin($context);
        if ($method==='GET') { return \Sgpm\Http\Response::json(array('items'=>(new MembershipRepository($db))->listing($company)),$this->requestId); }
        $service=new MembershipService($db,$users); $data=$request->body();
        if ($method==='DELETE') { $service->delete($company,$id,\Sgpm\Http\Request::version($data),$context); return new \Sgpm\Http\Response('',204); }
        $result=$service->save($company,$id,$data,$context);
        $response=\Sgpm\Http\Response::json($result,$this->requestId,$method==='POST'?201:200);
        if ($method==='POST') { return new \Sgpm\Http\Response($response->body(),201,array('Content-Type'=>'application/json; charset=utf-8','Cache-Control'=>'no-store','Location'=>'/api/v1/empresas/'.$company.'/usuarios/'.$result['id'])); }
        return $response;
    }
}
