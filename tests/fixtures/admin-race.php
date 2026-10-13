<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/bootstrap.php';
$config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php');
$config->assertTestDatabase();
$db=new Sgpm\Infrastructure\Database($config);
$userId=(int)$argv[1]; $version=(int)$argv[2]; $barrier=$argv[3];
$deadline=microtime(true)+10;
while (!is_file($barrier) && microtime(true)<$deadline) { usleep(10000); }
if (!is_file($barrier)) { exit(1); }
$users=new Sgpm\Modules\Usuarios\UserService($db,new Sgpm\Jobs\NotificationRepository($db,new Sgpm\Infrastructure\PayloadCipher($config)));
try {
    $users->update($userId,array('version'=>$version,'es_administrador'=>false),array('usuario_id'=>$userId,'ip'=>'127.0.0.5','request_id'=>bin2hex(random_bytes(16))));
    echo '200';
} catch (Sgpm\Http\HttpException $error) { echo $error->status(); }
