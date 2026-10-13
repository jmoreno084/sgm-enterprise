<?php
declare(strict_types=1);

return function ($assert) {
    $config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php');
    $config->assertTestDatabase();
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$message);
    if ($socket===false) { throw new RuntimeException('No se pudo reservar el puerto de pruebas.'); }
    $address=stream_socket_get_name($socket,false); fclose($socket);
    $port=(int)substr(strrchr($address,':'),1);
    $root=dirname(__DIR__,2);
    $log=tempnam(sys_get_temp_dir(),'sgpm-http-');
    $command=escapeshellarg(PHP_BINARY).' -S 127.0.0.1:'.$port.' -t '.escapeshellarg($root.'/public').' '.escapeshellarg($root.'/public/index.php');
    $process=proc_open($command,array(0=>array('pipe','r'),1=>array('file',$log,'a'),2=>array('file',$log,'a')),$pipes,$root);
    if (!is_resource($process)) { throw new RuntimeException('No se pudo iniciar el servidor HTTP local.'); }
    fclose($pipes[0]);
    $cookie=''; $csrf='';
    $call=function ($method,$path,$data=null,$sendCsrf=true) use ($port,&$cookie,&$csrf) {
        $handle=curl_init('http://127.0.0.1:'.$port.$path);
        $headers=array('Accept: application/json');
        if ($sendCsrf && $csrf!=='') { $headers[]='X-CSRF-Token: '.$csrf; }
        $options=array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>10);
        if ($cookie!=='') { $options[CURLOPT_COOKIE]='SGPMSESSID='.$cookie; }
        if ($data!==null) { $headers[]='Content-Type: application/json'; $options[CURLOPT_POSTFIELDS]=json_encode((object)$data); }
        $options[CURLOPT_HTTPHEADER]=$headers;
        curl_setopt_array($handle,$options); $response=curl_exec($handle);
        if ($response===false) { curl_close($handle); throw new RuntimeException('Solicitud HTTP local fallo.'); }
        $status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE); $size=(int)curl_getinfo($handle,CURLINFO_HEADER_SIZE); curl_close($handle);
        $header=substr($response,0,$size);
        if (preg_match_all('/Set-Cookie: SGPMSESSID=([^;\r\n]*)/i',$header,$matches)) { $cookie=end($matches[1]); }
        $body=json_decode(substr($response,$size),true);
        if (isset($body['data']['csrf'])) { $csrf=$body['data']['csrf']; }
        return array('status'=>$status,'body'=>$body,'headers'=>$header);
    };
    try {
        $ready=false;
        for ($attempt=0;$attempt<50;$attempt++) {
            $probe=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$message,0.1);
            if ($probe!==false) { fclose($probe); $ready=true; break; }
            usleep(100000);
        }
        if (!$ready) { throw new RuntimeException('Servidor HTTP de pruebas no disponible.'); }
        $result=$call('GET','/api/v1/usuarios');
        $assert($result['status']===401,'Usuario anonimo recibe 401.');
        $result=$call('GET','/api/v1/sesion');
        $assert($result['status']===200 && !$result['body']['data']['autenticado'],'CSRF anonimo disponible.');
        $assert(stripos($result['headers'],'Secure')!==false && stripos($result['headers'],'HttpOnly')!==false && stripos($result['headers'],'SameSite=Lax')!==false,'Atributos de cookie seguros.');
        $anonymousCookie=$cookie; $anonymousCsrf=$csrf;
        $result=$call('POST','/api/v1/sesion',array('correo'=>'admin@example.invalid','password'=>getenv('SGPM_FIXTURE_PASSWORD')),false);
        $assert($result['status']===403,'Login exige CSRF.');
        $result=$call('POST','/api/v1/sesion',array('correo'=>'admin@example.invalid','password'=>'incorrecta'));
        $assert($result['status']===401,'Login incorrecto recibe 401.');
        $result=$call('POST','/api/v1/sesion',array('correo'=>'admin@example.invalid','password'=>getenv('SGPM_FIXTURE_PASSWORD')));
        $assert($result['status']===200,'Login HTTP valido.');
        $assert($cookie!==$anonymousCookie && $csrf!==$anonymousCsrf,'Login rota identificador y CSRF.');
        $result=$call('GET','/api/v1/usuarios');
        $assert($result['status']===200 && isset($result['body']['data']['items']),'Administrador lista usuarios.');
        foreach ($result['body']['data']['items'] as $item) { $assert(!isset($item['password_hash'],$item['auth_version']),'HTTP no expone credenciales.'); }
        $result=$call('GET','/usuarios');
        $assert($result['status']===200,'Pagina de administracion disponible.');
        $result=$call('DELETE','/api/v1/sesion',array());
        $assert($result['status']===204,'Logout devuelve 204.');
        $result=$call('GET','/api/v1/sesion');
        $assert(!$result['body']['data']['autenticado'],'Sesion cerrada.');
        $result=$call('POST','/api/v1/sesion',array('correo'=>'geologo@example.invalid','password'=>getenv('SGPM_FIXTURE_PASSWORD')));
        $assert($result['status']===200,'Geologo inicia sesion.');
        $result=$call('GET','/api/v1/usuarios');
        $assert($result['status']===403,'Geologo no administra usuarios.');
        $result=$call('DELETE','/api/v1/sesion',array());
        $assert($result['status']===204,'Geologo cierra sesion.');
        $call('GET','/api/v1/sesion');
        $unknown=$call('POST','/api/v1/recuperaciones',array('correo'=>'desconocido@example.invalid'));
        $known=$call('POST','/api/v1/recuperaciones',array('correo'=>'admin@example.invalid'));
        $assert($unknown['status']===202 && $known['status']===202 && $unknown['body']['data']===$known['body']['data'],'Recuperacion HTTP uniforme.');
        $config=new Sgpm\Infrastructure\Configuration(require $root.'/config/config.php');
        $db=new Sgpm\Infrastructure\Database($config);
        $row=$db->execute("SELECT n.payload_cifrado FROM sgpm.notificaciones_outbox n JOIN sgpm.usuario u ON u.id=n.usuario_destino_id WHERE u.correo='admin@example.invalid' AND n.tipo='RECUPERACION' ORDER BY n.id DESC LIMIT 1")->fetch();
        $payload=(new Sgpm\Infrastructure\PayloadCipher($config))->decrypt($row['payload_cifrado']);
        $result=$call('POST','/api/v1/recuperaciones/confirmar',array('token'=>$payload['token'],'password'=>'clave HTTP restablecida','confirmacion'=>'clave HTTP restablecida'));
        $assert($result['status']===200,'Recuperacion funciona desde HTTP sin actor de sistema CLI.');
        $result=$call('POST','/api/v1/recuperaciones/confirmar',array('token'=>$payload['token'],'password'=>'clave HTTP restablecida','confirmacion'=>'clave HTTP restablecida'));
        $assert($result['status']===400,'Token HTTP no reutilizable.');
    } finally {
        proc_terminate($process); proc_close($process); unlink($log);
    }
};
