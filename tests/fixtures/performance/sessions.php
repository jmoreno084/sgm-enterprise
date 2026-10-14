<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/src/bootstrap.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
$config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,3).'/config/config.php'); $config->assertTestDatabase();
$root=dirname(__DIR__,3); $meta=json_decode(file_get_contents($root.'/storage/acceptance/performance.json'),true);
$server=json_decode(file_get_contents('/tmp/sgpm-acceptance.json'),true); $directory=dirname($server['ca']);
if (!preg_match('~^/tmp/sgpm-tls-[a-f0-9]{16}$~D',$directory) || count($meta['users'])!==50) { throw new RuntimeException('Entorno fixture inválido.'); }
ini_set('session.save_path',$directory.'/sessions'); $db=new Sgpm\Infrastructure\Database($config);
foreach ($meta['users'] as &$user) {
    $actor=$db->execute("SELECT id,auth_version FROM sgpm.usuario WHERE id=:id AND estado='ACTIVO' AND correo=:email",array('id'=>$user['id'],'email'=>$user['email']))->fetch();
    if (!$actor) { throw new RuntimeException('Usuario fixture inexistente.'); }
    session_id(bin2hex(random_bytes(16))); $session=new Sgpm\Auth\Session(); $session->authenticate((int)$actor['id'],(int)$actor['auth_version']);
    if (session_status()!==PHP_SESSION_ACTIVE || session_id()==='') { throw new RuntimeException('No se pudo preparar sesión fixture.'); }
    $user['cookie']='SGPMSESSID='.session_id(); $user['csrf']=$session->snapshot()['csrf'];
} unset($user);
file_put_contents($root.'/storage/acceptance/performance-sessions.json',json_encode($meta,JSON_PRETTY_PRINT)); chmod($root.'/storage/acceptance/performance-sessions.json',0600);
echo "50 sesiones distintas preparadas fuera del intervalo de medición. No se midió login.\n";
