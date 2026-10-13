<?php
declare(strict_types=1);
namespace Sgpm\Http;

class Request
{
    private $db;
    private $session;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Database $db,string $requestId)
    {
        $this->db=$db; $this->requestId=$requestId;
        $session=new \Sgpm\Auth\Session(); $session->open(); $this->session=$session->snapshot();
    }
    public function session(): array { return $this->session; }
    public function user(): array { return (new AuthorizationMiddleware($this->db))->user($this->session); }
    public function context(bool $authenticated=true): array
    {
        $ip=isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'127.0.0.1';
        if (filter_var($ip,FILTER_VALIDATE_IP)===false) { throw new HttpException(400,'ORIGEN_INVALIDO','Solicitud invalida.'); }
        $context=array('usuario_id'=>null,'sistema'=>true,'ip'=>$ip,'request_id'=>$this->requestId);
        if ($authenticated) { $context['usuario_id']=(int)$this->user()['id']; unset($context['sistema']); }
        return $context;
    }
    public function body(): array
    {
        \Sgpm\Auth\Csrf::verify($this->session,isset($_SERVER['HTTP_X_CSRF_TOKEN'])?$_SERVER['HTTP_X_CSRF_TOKEN']:'');
        if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH']>1048576) { throw new HttpException(413,'CUERPO_EXCESIVO','La solicitud supera el tamano permitido.'); }
        $type=isset($_SERVER['CONTENT_TYPE'])?strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE'])[0])):'';
        if ($type!=='application/json') { throw new HttpException(415,'TIPO_INVALIDO','Envia la solicitud como JSON.'); }
        $stream=fopen('php://input','rb');
        $raw=stream_get_contents($stream,1048577); fclose($stream);
        if (strlen($raw)>1048576) { throw new HttpException(413,'CUERPO_EXCESIVO','La solicitud supera el tamano permitido.'); }
        $data=json_decode($raw,true);
        if (!is_array($data) || substr(ltrim($raw),0,1)!=='{') { throw new HttpException(400,'JSON_INVALIDO','La solicitud no es valida.'); }
        return $data;
    }
    public static function text(array $data,string $key): string
    {
        if (!isset($data[$key]) || !is_string($data[$key])) { throw new HttpException(400,'CAMPO_INVALIDO','Revisa los campos del formulario.'); }
        return $data[$key];
    }
    public static function version(array $data): int
    {
        if (!isset($data['version']) || !is_int($data['version']) || $data['version']<1) { throw new HttpException(400,'VERSION_REQUERIDA','Indica la version actual del registro.'); }
        return $data['version'];
    }
}
