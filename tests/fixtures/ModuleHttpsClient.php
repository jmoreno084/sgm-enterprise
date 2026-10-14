<?php
declare(strict_types=1);
require_once __DIR__.'/LocalApache.php';

class SgpmModuleHttpsClient
{
    private $server;
    private $jar;
    private $csrf='';
    private $ip;
    public function __construct(SgpmLocalApache $server)
    {
        $this->server=$server; $this->jar=tempnam($server->directory(),'module-cookies-'); chmod($this->jar,0600);
        $this->ip='127.20.'.random_int(1,254).'.'.random_int(1,254);
    }
    public function call(string $method,string $path,array $body=array(),bool $multipart=false,bool $csrf=true): array
    {
        $curl=curl_init($this->server->url().$path);
        $headers=array('Accept: application/json');
        if ($csrf) { $headers[]='X-CSRF-Token: '.$this->csrf; }
        $options=array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_CUSTOMREQUEST=>$method,
            CURLOPT_CAINFO=>$this->server->ca(),CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_COOKIEFILE=>$this->jar,CURLOPT_COOKIEJAR=>$this->jar,CURLOPT_TIMEOUT=>15,CURLOPT_INTERFACE=>$this->ip);
        if ($method!=='GET') {
            if ($multipart) { $options[CURLOPT_POSTFIELDS]=$body; }
            else { $options[CURLOPT_POSTFIELDS]=json_encode((object)$body); $headers[]='Content-Type: application/json'; }
        }
        $options[CURLOPT_HTTPHEADER]=$headers; curl_setopt_array($curl,$options);
        $raw=curl_exec($curl);
        if ($raw===false) { curl_close($curl); throw new RuntimeException('Fallo HTTPS verificado en contrato.'); }
        $size=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
        $text=substr($raw,$size); $json=json_decode($text,true);
        if (isset($json['data']['csrf'])) { $this->csrf=$json['data']['csrf']; }
        return array('status'=>$status,'json'=>$json,'text'=>$text,'headers'=>substr($raw,0,$size));
    }
    public function login(string $email,string $password)
    {
        $this->call('GET','/api/v1/sesion');
        $result=$this->call('POST','/api/v1/sesion',array('correo'=>$email,'password'=>$password));
        if ($result['status']!==200) { throw new RuntimeException('Login fixture fallo con HTTP '.$result['status']); }
    }
    public function close() { if (is_file($this->jar)) { unlink($this->jar); } }
}
