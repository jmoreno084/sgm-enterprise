<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/LocalApache.php';

return function ($assert) {
    $server=new SgpmLocalApache(); $jar=null;
    try {
        $server->start();
        $jar=tempnam($server->directory(),'cookies-'); chmod($jar,0600);
        $csrf='';
        $call=function ($method,$path,$body=null,$https=true) use ($server,$jar,&$csrf) {
            $curl=curl_init(($https?$server->url():$server->httpUrl()).$path);
            $headers=array('Accept: application/json');
            if ($csrf!=='') { $headers[]='X-CSRF-Token: '.$csrf; }
            $options=array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_CUSTOMREQUEST=>$method,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CAINFO=>$server->ca(),CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>10);
            if ($body!==null) { $headers[]='Content-Type: application/json'; $options[CURLOPT_POSTFIELDS]=json_encode((object)$body); }
            $options[CURLOPT_HTTPHEADER]=$headers; curl_setopt_array($curl,$options); $raw=curl_exec($curl);
            if ($raw===false) { curl_close($curl); throw new RuntimeException('Solicitud HTTPS local fallo.'); }
            $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); $size=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE);
            $verified=(int)curl_getinfo($curl,CURLINFO_SSL_VERIFYRESULT); curl_close($curl);
            $head=substr($raw,0,$size); $content=substr($raw,$size); $data=json_decode($content,true);
            if (isset($data['data']['csrf'])) { $csrf=$data['data']['csrf']; }
            return array('status'=>$status,'headers'=>$head,'body'=>$data,'content'=>$content,'verify'=>$verified);
        };
        $redirect=$call('GET','/login',null,false);
        $assert($redirect['status']===301 && strpos($redirect['headers'],$server->url().'/login')!==false,'HTTP redirige 301 a HTTPS preservando ruta.');
        $login=$call('GET','/login');
        $assert($login['status']===200 && $login['verify']===0,'Login servido con certificado de prueba validado.');
        $assert(stripos($login['headers'],'Strict-Transport-Security: max-age=31536000')!==false,'HSTS en HTTPS.');
        $assert(stripos($login['headers'],'X-Content-Type-Options: nosniff')!==false,'nosniff en HTTPS.');
        $page=new DOMDocument(); @$page->loadHTML($login['content']); $xpath=new DOMXPath($page);
        $assert($xpath->query('//label[@for="correo"]')->length===1 && $xpath->query('//label[@for="password"]')->length===1,'Login contiene etiquetas accesibles.');
        foreach (array('/assets/js/modules/auth.js','/assets/js/modules/http.js','/assets/vendor/bootstrap/bootstrap.min.css') as $asset) {
            $response=$call('GET',$asset); $assert($response['status']===200 && strlen($response['content'])>0,'Activo disponible por HTTPS: '.$asset);
        }
        foreach (array('/config/config.php','/src/bootstrap.php','/tests/fixtures/acceptance.php','/storage/sessions/','/.git/config') as $private) {
            $response=$call('GET',$private); $assert(in_array($response['status'],array(403,404),true),'Ruta privada no expuesta: '.$private);
        }
        $anonymous=$call('GET','/api/v1/sesion');
        $assert($anonymous['status']===200 && !$anonymous['body']['data']['autenticado'],'Sesion anonima por TLS.');
        $oldCsrf=$csrf;
        $session=$call('POST','/api/v1/sesion',array('correo'=>'admin@example.invalid','password'=>getenv('SGPM_FIXTURE_PASSWORD')));
        $assert($session['status']===200 && $csrf!==$oldCsrf,'Login TLS rota CSRF.');
        $users=$call('GET','/usuarios'); $assert($users['status']===200,'Cookie Secure autentica por TLS sin encabezado manual.');
        $assert(strpos($users['content'],'id="membership-form"')!==false,'Formulario de membresias servido al Administrador.');
        $logout=$call('DELETE','/api/v1/sesion',array()); $assert($logout['status']===204,'Logout TLS correcto.');
        $denied=$call('GET','/usuarios'); $assert($denied['status']===401,'Sesion eliminada no accede a usuarios.');
        $recovery=$call('GET','/recuperar');
        $assert($recovery['status']===200 && strpos($recovery['content'],'autocomplete="new-password"')!==false,'Formulario de recuperacion disponible.');
    } finally { if ($jar && is_file($jar)) { unlink($jar); } $server->close(); }
};
