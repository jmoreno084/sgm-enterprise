<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/bootstrap.php';
$config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php'); $config->assertTestDatabase();
$db=new Sgpm\Infrastructure\Database($config); $input=json_decode(stream_get_contents(STDIN),true); $deadline=microtime(true)+10;
while (!is_file($input['barrier']) && microtime(true)<$deadline) { usleep(10000); }
if (!is_file($input['barrier'])) { exit(1); }
try {
    $result=(new Sgpm\Modules\Vecinas\ProjectNeighborService($db))->save($input['project'],0,array('nueva_identidad'=>array('nombre'=>$input['name'])),$input['context']);
    echo json_encode($result);
} catch (Sgpm\Http\HttpException $error) { echo json_encode(array('error'=>$error->status())); }
