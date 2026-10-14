<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/bootstrap.php';
$config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php'); $config->assertTestDatabase();
$db=new Sgpm\Infrastructure\Database($config); $input=json_decode(stream_get_contents(STDIN),true);
$deadline=microtime(true)+10;
while (!is_file($input['barrier']) && microtime(true)<$deadline) { usleep(10000); }
if (!is_file($input['barrier'])) { exit(1); }
try {
    (new Sgpm\Modules\Proyectos\ExpedienteService($db))->save($input['project'],0,$input['data'],$input['context']); echo '201';
} catch (Sgpm\Http\HttpException $error) { echo $error->status(); }
