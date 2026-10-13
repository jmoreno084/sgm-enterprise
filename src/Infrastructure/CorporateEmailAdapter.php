<?php
declare(strict_types=1);
namespace Sgpm\Infrastructure;

class CorporateEmailAdapter
{
    private $config;
    private $transport;
    private $profile;
    public function __construct(Configuration $config,LibCurlModel $transport,array $profile=array())
    { $this->config=$config; $this->transport=$transport; $this->profile=$profile; }
    public function send(string $email,array $payload): array
    {
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !isset($payload['asunto'],$payload['mensaje'])
            || !is_string($payload['asunto']) || !is_string($payload['mensaje']) || preg_match('/[\r\n]/',$payload['asunto'])) {
            return array('estado'=>'FALLIDO','http_status'=>null,'error_codigo'=>'PAYLOAD_INVALIDO');
        }
        if ($this->config->get('email_mode')==='double') {
            // No external calls in double mode; production conformity remains unverified.
            return array('estado'=>'ACEPTADO','http_status'=>200,'error_codigo'=>'DOBLE_LOCAL');
        }
        $params=array('USERKEY'=>$this->config->get('email_userkey'),'APIKEY'=>$this->config->get('email_apikey'),
            'email'=>$email,'asunto'=>$payload['asunto'],'mensaje'=>$payload['mensaje']);
        $body=$this->transport->curl_download($this->config->get('email_url'),$params);
        return self::classify($this->transport->status(),$body,$this->transport->error(),$this->profile);
    }
    public static function classify(int $status,string $body,int $error,array $profile): array
    {
        $state='INDETERMINADO'; $code='RESPUESTA_DESCONOCIDA';
        if ($error!==0) { $code='TRANSPORTE_AMBIGUO'; }
        elseif ($status>=400 && $status<=599) { $state='FALLIDO'; $code='RECHAZO_HTTP'; }
        elseif (!empty($profile['verified'])) {
            if (isset($profile['negative']) && in_array($body,$profile['negative'],true)) { $state='FALLIDO'; $code='RECHAZO_PROVEEDOR'; }
            elseif ($status>=200 && $status<300 && $body!=='' && isset($profile['positive']) && in_array($body,$profile['positive'],true)) { $state='ACEPTADO'; $code=null; }
        }
        return array('estado'=>$state,'http_status'=>$status>0?$status:null,'error_codigo'=>$code);
    }
}
