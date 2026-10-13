<?php
declare(strict_types=1);
return function ($assert) {
    $profile=require dirname(__DIR__).'/fixtures/email-double.php';
    $cases=array(array(200,'fixture-accepted',0,'ACEPTADO'),array(200,'fixture-rejected',0,'FALLIDO'),array(200,'unknown',0,'INDETERMINADO'),array(500,'failure',0,'FALLIDO'),array(0,'',28,'INDETERMINADO'),array(200,'',0,'INDETERMINADO'));
    foreach ($cases as $case) {
        $result=Sgpm\Infrastructure\CorporateEmailAdapter::classify($case[0],$case[1],$case[2],$profile);
        $assert($result['estado']===$case[3],'Clasificacion de correo contractual.');
        $assert(!isset($result['body']),'Respuesta cruda no expuesta.');
    }
    $result=Sgpm\Infrastructure\CorporateEmailAdapter::classify(200,'fixture-accepted',0,array());
    $assert($result['estado']==='INDETERMINADO','Sin perfil no se declara aceptado.');
    $values=require dirname(__DIR__,2).'/config/config.php';
    $values['email_mode']='real'; $values['email_userkey']='fixture-userkey'; $values['email_apikey']='fixture-apikey';
    $transport=new class extends Sgpm\Infrastructure\LibCurlModel {
        public $params=array();
        public $url='';
        public function curl_download(string $url,array $params=array()): string { $this->params=$params; $this->url=$url; return 'fixture-accepted'; }
        public function status(): int { return 200; }
        public function error(): int { return 0; }
    };
    $adapter=new Sgpm\Infrastructure\CorporateEmailAdapter(new Sgpm\Infrastructure\Configuration($values),$transport,$profile);
    $sent=$adapter->send('fixture@example.invalid',array('asunto'=>'Prueba','mensaje'=>'Texto'));
    $assert($sent['estado']==='ACEPTADO','Perfil de fixture positivo.');
    $assert(array_keys($transport->params)===array('USERKEY','APIKEY','email','asunto','mensaje'),'Cinco parametros corporativos exactos.');
    $assert($transport->url==='https://sigmin.automatizzar.com/admin_emails/sendEmail.php','Destino corporativo fijo.');
    $assert($adapter->send('fixture@example.invalid',array('asunto'=>"Prueba\r\nInyectada",'mensaje'=>'Texto'))['estado']==='FALLIDO','Asunto con salto de linea rechazado.');
};
