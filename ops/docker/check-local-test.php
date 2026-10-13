<?php
declare(strict_types=1);

// Non-destructive smoke for the persistent fixture. No production or real email.
require dirname(__DIR__, 2).'/src/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
$config = new Sgpm\Infrastructure\Configuration(require dirname(__DIR__, 2).'/config/config.php');
$config->assertTestDatabase();
if ($config->get('test_database') !== 'sgpm_local_test' || $config->get('email_mode') !== 'double') { throw new RuntimeException('Solo ambiente local persistente/double.'); }
$credentials = json_decode(stream_get_contents(STDIN), true);
if (!is_array($credentials) || !isset($credentials['admin_email'], $credentials['password'])) { throw new RuntimeException('Credenciales por entrada estandar requeridas.'); }
$checks = 0;
$smokeIp = '127.100.'.random_int(1, 254).'.'.random_int(1, 254);
function verifyLocal(bool $condition, string $label)
{
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++;
    echo 'PASS '.$label."\n";
}
class SgpmPersistentSmokeClient
{
    private $curl;
    private $jar;
    private $csrf = '';
    public function __construct()
    {
        global $smokeIp;
        $this->jar = tempnam(sys_get_temp_dir(), 'sgpm-smoke-');
        $this->curl = curl_init();
        curl_setopt_array($this->curl, array(CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CAINFO=>'/etc/sgpm-local/server.crt', CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_COOKIEFILE=>$this->jar, CURLOPT_COOKIEJAR=>$this->jar,
            CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15, CURLOPT_INTERFACE=>$smokeIp));
    }
    public function call(string $method, string $path, array $body=array(), bool $csrf=true): array
    {
        $headers = array('Accept: application/json');
        if ($method !== 'GET') {
            $headers[] = 'Content-Type: application/json';
            if ($csrf) { $headers[]='X-CSRF-Token: '.$this->csrf; }
        }
        curl_setopt($this->curl, CURLOPT_URL, 'https://localhost:18443'.$path);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        if ($method !== 'GET') { curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode((object)$body)); }
        else { curl_setopt($this->curl, CURLOPT_HTTPGET, true); }
        $raw = curl_exec($this->curl);
        if ($raw === false) { throw new RuntimeException('Fallo transporte HTTPS verificado.'); }
        $status=(int)curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $decoded=json_decode($raw, true);
        if (isset($decoded['data']['csrf'])) { $this->csrf=$decoded['data']['csrf']; }
        return array('status'=>$status, 'json'=>$decoded, 'raw'=>$raw);
    }
    public function close()
    {
        if ($this->curl) { curl_close($this->curl); $this->curl=null; }
        if (is_file($this->jar)) { unlink($this->jar); }
    }
}
$admin = new SgpmPersistentSmokeClient();
$geo = new SgpmPersistentSmokeClient();
try {
    $loginPage=$admin->call('GET','/login');
    verifyLocal($loginPage['status']===200 && strpos($loginPage['raw'],'auth-form')!==false, 'Login HTML sobre HTTPS verificado');
    $session=$admin->call('GET','/api/v1/sesion');
    verifyLocal($session['status']===200 && !$session['json']['data']['autenticado'], 'Sesion anonima y CSRF');
    $missing=$admin->call('POST','/api/v1/sesion',array('correo'=>$credentials['admin_email'],'password'=>$credentials['password']),false);
    verifyLocal($missing['status']===403, 'POST sin CSRF rechazado');
    $bad=$admin->call('POST','/api/v1/sesion',array('correo'=>$credentials['admin_email'],'password'=>'incorrecta-fixture'));
    verifyLocal($bad['status']===401 && isset($bad['json']['error']['message']), 'Clave incorrecta 401 amigable');
    $login=$admin->call('POST','/api/v1/sesion',array('correo'=>$credentials['admin_email'],'password'=>$credentials['password']));
    verifyLocal($login['status']===200 && $login['json']['data']['usuario']['es_administrador'], 'Administrador autentica');
    verifyLocal($admin->call('GET','/usuarios')['status']===200, 'Pantalla de usuarios autorizada');
    $session=$admin->call('GET','/api/v1/sesion');
    $companies=$session['json']['data']['empresas'];
    verifyLocal(count($companies)===2, 'Empresas A y B disponibles');
    $company=(int)$companies[0]['id'];
    $email='smoke-'.bin2hex(random_bytes(6)).'@example.invalid';
    $password=bin2hex(random_bytes(16));
    $user=array('correo'=>$email,'nombre'=>'Verificación José Muñoz','numero_documento'=>'CÉDULA-Ñ','estado'=>'ACTIVO','es_administrador'=>false,'password'=>$password);
    $created=$admin->call('POST','/api/v1/usuarios',$user);
    verifyLocal($created['status']===201, 'Creacion de usuario de verificacion');
    $id=(int)$created['json']['data']['id'];
    $duplicate=$user; $duplicate['correo']='  '.strtoupper($email).'  ';
    verifyLocal($admin->call('POST','/api/v1/usuarios',$duplicate)['status']===409, 'Duplicado normalizado 409');
    $loaded=$admin->call('GET','/api/v1/usuarios/'.$id)['json']['data'];
    verifyLocal($loaded['nombre']===$user['nombre'] && $loaded['numero_documento']===$user['numero_documento'], 'UTF-8 conservado');
    unset($user['password']); $user['version']=(int)$loaded['version'];
    $user['nombre']='Verificación primera edición';
    verifyLocal($admin->call('PUT','/api/v1/usuarios/'.$id,$user)['status']===200, 'Edicion versionada');
    $user['nombre']='Edición obsoleta';
    verifyLocal($admin->call('PUT','/api/v1/usuarios/'.$id,$user)['status']===409, 'Concurrencia obsoleta 409');
    $member=array('usuario_id'=>$id,'rol'=>'GEOLOGO','estado'=>'ACTIVO');
    $assigned=$admin->call('POST','/api/v1/empresas/'.$company.'/usuarios',$member);
    verifyLocal($assigned['status']===201, 'Asignacion GEOLOGO en A');
    $membership=$assigned['json']['data'];
    verifyLocal($admin->call('POST','/api/v1/empresas/'.$company.'/usuarios',$member)['status']===409, 'Membresia duplicada 409');
    $geo->call('GET','/api/v1/sesion');
    verifyLocal($geo->call('POST','/api/v1/sesion',array('correo'=>$email,'password'=>$password))['status']===200, 'Geologo autentica');
    verifyLocal($geo->call('GET','/api/v1/usuarios')['status']===403 && $geo->call('GET','/api/v1/empresas/'.$company.'/usuarios')['status']===403, 'RBAC administrativo 403');
    $authorized=$geo->call('GET','/api/v1/sesion')['json']['data']['empresas'];
    verifyLocal(count($authorized)===1 && (int)$authorized[0]['id']===$company, 'Acceso solo A sin herencia B');
    $revoked=$admin->call('DELETE','/api/v1/empresas/'.$company.'/usuarios/'.$membership['id'],array('version'=>(int)$membership['version']));
    verifyLocal($revoked['status']===204 && count($geo->call('GET','/api/v1/sesion')['json']['data']['empresas'])===0, 'Revocacion efectiva siguiente peticion');
    $known=$geo->call('POST','/api/v1/recuperaciones',array('correo'=>$email));
    $unknown=$geo->call('POST','/api/v1/recuperaciones',array('correo'=>'unknown-'.bin2hex(random_bytes(5)).'@example.invalid'));
    verifyLocal($known['status']===202 && $unknown['status']===202 && $known['json']['data']['message']===$unknown['json']['data']['message'], 'Recuperacion uniforme 202');
    $db=new Sgpm\Infrastructure\Database($config);
    $row=$db->execute("SELECT payload_cifrado FROM sgpm.notificaciones_outbox WHERE tipo='RECUPERACION' AND usuario_destino_id=:id ORDER BY id DESC LIMIT 1",array('id'=>$id))->fetch();
    $payload=(new Sgpm\Infrastructure\PayloadCipher($config))->decrypt($row['payload_cifrado']);
    $newPassword=bin2hex(random_bytes(16));
    $confirmation=array('token'=>$payload['token'],'password'=>$newPassword,'confirmacion'=>$newPassword);
    verifyLocal($geo->call('POST','/api/v1/recuperaciones/confirmar',$confirmation)['status']===200, 'Recuperacion cambia clave');
    verifyLocal($geo->call('POST','/api/v1/recuperaciones/confirmar',$confirmation)['status']===400, 'Token de un solo uso');
    $geo->call('GET','/api/v1/sesion');
    verifyLocal($geo->call('POST','/api/v1/sesion',array('correo'=>$email,'password'=>$newPassword))['status']===200, 'Nueva clave autentica');
    verifyLocal($geo->call('DELETE','/api/v1/sesion')['status']===204 && $geo->call('GET','/usuarios')['status']===401, 'Logout invalida autorizacion');
    $current=$admin->call('GET','/api/v1/usuarios/'.$id)['json']['data'];
    $user['nombre']=$current['nombre']; $user['version']=(int)$current['version']; $user['estado']='INACTIVO';
    verifyLocal($admin->call('PUT','/api/v1/usuarios/'.$id,$user)['status']===200, 'Usuario de verificacion desactivado sin borrar historial');
    verifyLocal((int)$db->execute("SELECT CASE WHEN has_table_privilege(current_user,'sgpm.schema_migrations','SELECT') THEN 1 ELSE 0 END")->fetchColumn()===0, 'Runtime separado de metadatos migradores');
    verifyLocal($config->get('email_mode')==='double' && $config->get('email_userkey')==='' && $config->get('email_apikey')==='', 'Correo double sin credenciales reales');
    $admin->call('DELETE','/api/v1/sesion');
    echo 'Resultado: '.$checks." PASS, 0 FAIL.\n";
} catch (Throwable $error) {
    fwrite(STDERR,'FAIL: '.$error->getMessage()."\n");
    exit(1);
} finally { $admin->close(); $geo->close(); }
