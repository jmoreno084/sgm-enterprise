<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/LocalApache.php';

class SgpmHttpFixtureTransport extends Sgpm\Infrastructure\LibCurlModel
{
    private $url;
    private $ca;
    public function __construct(string $url,string $ca) { $this->url=$url; $this->ca=$ca; }
    public function curl_download(string $url,array $params=array()): string
    {
        if ($url!=='https://sigmin.automatizzar.com/admin_emails/sendEmail.php') { throw new InvalidArgumentException('URL de prueba invalida.'); }
        return $this->transfer($this->url,$params,array(CURLOPT_CAINFO=>$this->ca,CURLOPT_TIMEOUT=>1,CURLOPT_CONNECTTIMEOUT=>1));
    }
}

return function ($assert) {
    $values=require dirname(__DIR__,2).'/config/config.php';
    (new Sgpm\Infrastructure\Configuration($values))->assertTestDatabase();
    $values['email_mode']='real'; $values['email_userkey']='fixture-userkey'; $values['email_apikey']='fixture-apikey';
    $config=new Sgpm\Infrastructure\Configuration($values);
    $profile=require dirname(__DIR__).'/fixtures/email-double.php';
    $server=new SgpmLocalApache();
    try {
        $server->start();
        $cases=array('accepted'=>'ACEPTADO','negative'=>'FALLIDO','unknown'=>'INDETERMINADO','empty'=>'INDETERMINADO','failure'=>'FALLIDO','timeout'=>'INDETERMINADO','partial'=>'INDETERMINADO');
        foreach ($cases as $mode=>$expected) {
            $transport=new SgpmHttpFixtureTransport($server->url().'/__fixture_mail/'.$mode,$server->ca());
            $adapter=new Sgpm\Infrastructure\CorporateEmailAdapter($config,$transport,$profile);
            $result=$adapter->send('fixture@example.invalid',array('asunto'=>'Prueba correo áé','mensaje'=>'Mensaje con & y <etiqueta>'));
            $assert($result['estado']===$expected,'Doble HTTP/TLS: '.$mode.' -> '.$expected.'; obtenido '.$result['estado']);
            if ($mode==='accepted') { $assert($transport->status()===200 && $transport->error()===0,'POST real con cinco parametros y UTF-8 correcto.'); }
            if ($mode==='timeout' || $mode==='partial') { $assert($transport->error()!==0,'Fallo de transporte real observado: '.$mode.' (HTTP '.$transport->status().', cURL '.$transport->error().').'); }
        }
        $params=array('USERKEY'=>'fixture-userkey','APIKEY'=>'fixture-apikey','email'=>'fixture@example.invalid','asunto'=>'Prueba correo áé','mensaje'=>'Mensaje con & y <etiqueta>');
        $wrongHost=new SgpmHttpFixtureTransport(str_replace('localhost','127.0.0.1',$server->url()).'/__fixture_mail/accepted',$server->ca());
        $wrongHost->curl_download($config->get('email_url'),$params);
        $assert($wrongHost->error()!==0,'TLS rechaza nombre de host incorrecto.');
    } finally { $server->close(); }
};
