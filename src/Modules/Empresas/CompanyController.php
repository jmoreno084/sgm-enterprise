<?php
declare(strict_types=1);
namespace Sgpm\Modules\Empresas;
use Sgpm\Http\Request;
use Sgpm\Http\Response;
use Sgpm\Auth\ResourceAccess;

class CompanyController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(string $method,int $id=0): Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $request=new Request($db,$this->requestId); $context=$request->context();
        $service=new CompanyService($db);
        if ($method==='GET') { return Response::json($id?$service->find($id,$context):(new CompanyRepository($db))->listing($request->user(),$_GET),$this->requestId); }
        $data=$request->body();
        if ($method==='DELETE') { $service->delete($id,Request::version($data),$context); return new Response('',204); }
        $result=$service->save($id,$data,$context);
        return $method==='POST'?Response::created($result,$this->requestId,'/api/v1/empresas/'.$result['id']):Response::json($result,$this->requestId);
    }
    public function logo(string $method,int $id): Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $request=new Request($db,$this->requestId); $context=$request->context();
        $access=new ResourceAccess($db); $access->company($id,'consultar',$context); $storage=new LogoStorage();
        if ($method==='GET') {
            $name=$db->execute('SELECT logo_url FROM sgpm.empresa WHERE id=:id',array('id'=>$id))->fetchColumn();
            if (!$name) { ResourceAccess::missing(); }
            $path=$storage->path($name);
            return new Response(file_get_contents($path),200,array('Content-Type'=>(new \finfo(FILEINFO_MIME_TYPE))->file($path),'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store'));
        }
        $access->admin($context);
        \Sgpm\Auth\Csrf::verify($request->session(),isset($_SERVER['HTTP_X_CSRF_TOKEN'])?$_SERVER['HTTP_X_CSRF_TOKEN']:'');
        if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH']>6*1024*1024) { throw new \Sgpm\Http\HttpException(413,'ARCHIVO_GRANDE','El logo admite hasta 5 MiB.'); }
        if (!isset($_FILES['archivo'])) { throw new \Sgpm\Http\HttpException(400,'ARCHIVO_REQUERIDO','Selecciona una imagen PNG o JPEG.'); }
        $version=filter_var(isset($_POST['version'])?$_POST['version']:'',FILTER_VALIDATE_INT);
        if ($version===false || $version<1) { throw new \Sgpm\Http\HttpException(400,'VERSION_REQUERIDA','Indica la versión actual de la empresa.'); }
        $name=$storage->store($_FILES['archivo']);
        try {
            $result=\Sgpm\Infrastructure\Mutation::run($db,$context,function () use ($access,$context,$db,$id,$version,$name) {
                $access->admin($context); $row=(new CompanyRepository($db))->find($id,true);
                \Sgpm\Infrastructure\Mutation::version($row,array('version'=>$version));
                $db->execute('UPDATE sgpm.empresa SET logo_url=:name,version=version+1 WHERE id=:id',array('id'=>$id,'name'=>$name));
                return (new CompanyRepository($db))->find($id);
            });
        } catch (\Throwable $error) { $storage->discard($name); throw $error; }
        return Response::json($result,$this->requestId);
    }
}
