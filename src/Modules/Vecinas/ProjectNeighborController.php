<?php
declare(strict_types=1);
namespace Sgpm\Modules\Vecinas;
use Sgpm\Http\Response;
use Sgpm\Http\Request;

class ProjectNeighborController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(string $method,int $project,int $neighbor=0,bool $resolve=false): Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $request=new Request($db,$this->requestId); $context=$request->context();
        (new \Sgpm\Auth\ResourceAccess($db))->project($project,'consultar',$context);
        if ($method==='GET') {
            $page=\Sgpm\Infrastructure\Fields::page($_GET); $repository=new ProjectNeighborRepository($db);
            $total=(int)$db->execute('SELECT count(*) FROM sgpm.proyecto_empresas_vecinas WHERE proyecto_id=:id',array('id'=>$project))->fetchColumn();
            $rows=$db->execute('SELECT v.*,n.nombre,n.nit FROM sgpm.proyecto_empresas_vecinas v JOIN sgpm.empresa_vecina n ON n.id=v.empresa_vecina_id WHERE v.proyecto_id=:id ORDER BY v.orden_registro LIMIT :limit OFFSET :offset',array('id'=>$project,'limit'=>$page['page_size'],'offset'=>$page['offset']))->fetchAll();
            return Response::json(array('items'=>array_map(array(\Sgpm\Infrastructure\Fields::class,'row'),$rows),'total'=>$total,'page'=>$page['page'],'page_size'=>$page['page_size']),$this->requestId);
        }
        $service=new ProjectNeighborService($db); $data=$request->body();
        if ($resolve) { \Sgpm\Infrastructure\Fields::only($data,array('nit')); return Response::json($service->resolve($project,Request::text($data,'nit'),$context),$this->requestId); }
        if ($method==='DELETE') { $service->delete($project,$neighbor,Request::version($data),$context); return new Response('',204); }
        $result=$service->save($project,$neighbor,$data,$context);
        return $method==='POST'?Response::created($result,$this->requestId,'/api/v1/proyectos/'.$project.'/vecinas/'.$result['empresa_vecina_id']):Response::json($result,$this->requestId);
    }
}
