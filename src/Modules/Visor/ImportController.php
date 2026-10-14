<?php
declare(strict_types=1);
namespace Sgpm\Modules\Visor;

class ImportController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(int $project): \Sgpm\Http\Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $request=new \Sgpm\Http\Request($db,$this->requestId); $context=$request->context();
        (new \Sgpm\Auth\ResourceAccess($db))->project($project,'consultar',$context);
        \Sgpm\Auth\Csrf::verify($request->session(),isset($_SERVER['HTTP_X_CSRF_TOKEN'])?$_SERVER['HTTP_X_CSRF_TOKEN']:'');
        if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH']>21*1024*1024) { throw new \Sgpm\Http\HttpException(413,'ARCHIVO_GRANDE','La capa admite hasta 20 MiB.'); }
        if (!isset($_FILES['archivo'])) { throw new \Sgpm\Http\HttpException(400,'ARCHIVO_REQUERIDO','Selecciona un archivo geográfico.'); }
        $file=$_FILES['archivo'];
        if ($file['error']!==UPLOAD_ERR_OK) { throw new \Sgpm\Http\HttpException(in_array($file['error'],array(UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE),true)?413:400,'ARCHIVO_INVALIDO','No fue posible recibir la capa.'); }
        if (!is_uploaded_file($file['tmp_name'])) { throw new \Sgpm\Http\HttpException(400,'ARCHIVO_INVALIDO','La carga no es válida.'); }
        return \Sgpm\Http\Response::json((new ImportService())->read($file['tmp_name'],$file['name']),$this->requestId);
    }
}
