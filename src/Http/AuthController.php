<?php
declare(strict_types=1);
namespace Sgpm\Http;

class AuthController
{
    private $db;
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId)
    { $this->config=$config; $this->requestId=$requestId; $this->db=new \Sgpm\Infrastructure\Database($config); }
    public function session(string $method): Response
    {
        $request=new Request($this->db,$this->requestId);
        $session=$request->session();
        if ($method==='GET') {
            $result=array('csrf'=>$session['csrf'],'autenticado'=>false);
            if (isset($session['usuario_id'])) {
                $user=$request->user();
                $result['autenticado']=true;
                $result['usuario']=(new \Sgpm\Modules\Usuarios\UserRepository($this->db))->find((int)$user['id']);
                $params=array();
                $sql="SELECT e.id,e.razon_social FROM sgpm.empresa e WHERE e.estado='ACTIVO'";
                if (!\Sgpm\Auth\Authorization::permits($user,'admin')) {
                    $sql.=" AND EXISTS (SELECT 1 FROM sgpm.empresa_usuarios m WHERE m.empresa_id=e.id AND m.usuario_id=:usuario AND m.estado='ACTIVO')"; $params['usuario']=(int)$user['id'];
                }
                $result['empresas']=$this->db->execute($sql.' ORDER BY e.id',$params)->fetchAll();
            }
            return Response::json($result,$this->requestId);
        }
        $data=$request->body();
        $auth=new \Sgpm\Auth\AuthenticationService($this->db);
        if ($method==='DELETE') {
            $auth->logout($session,$request->context()); (new \Sgpm\Auth\Session())->destroy();
            return new Response('',204);
        }
        $identity=$auth->login(Request::text($data,'correo'),Request::text($data,'password'),$request->context(false));
        $manager=new \Sgpm\Auth\Session(); $manager->authenticate($identity['usuario_id'],$identity['auth_version']); $newSession=$manager->snapshot();
        return Response::json(array('csrf'=>$newSession['csrf'],'usuario'=>(new \Sgpm\Modules\Usuarios\UserRepository($this->db))->find($identity['usuario_id'])),$this->requestId);
    }
    public function recovery(bool $confirm): Response
    {
        $request=new Request($this->db,$this->requestId); $data=$request->body();
        $cipher=new \Sgpm\Infrastructure\PayloadCipher($this->config);
        $service=new \Sgpm\Auth\RecoveryService($this->db,new \Sgpm\Jobs\NotificationRepository($this->db,$cipher),$this->config);
        if ($confirm) {
            $service->confirm(Request::text($data,'token'),Request::text($data,'password'),Request::text($data,'confirmacion'),$request->context(false));
            return Response::json(array('message'=>'La clave fue actualizada. Inicia sesion nuevamente.'),$this->requestId);
        }
        $service->request(Request::text($data,'correo'),$request->context(false));
        return Response::json(array('message'=>'Si el correo tiene una cuenta activa, recibiras las instrucciones de recuperacion.'),$this->requestId,202);
    }
}
