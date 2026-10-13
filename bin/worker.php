<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    $config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__).'/config/config.php');
    if ($config->get('env')==='production' && !in_array('--production',$argv,true)) { throw new RuntimeException('Produccion requiere opcion explicita.'); }
    if (in_array('--env=test',$argv,true)) { $config->assertTestDatabase(); }
    $db=new Sgpm\Infrastructure\Database($config);
    $profileFile=dirname(__DIR__).'/config/email-response.php';
    $profile=is_file($profileFile)?require $profileFile:array();
    $worker=new Sgpm\Jobs\NotificationWorker($db,new Sgpm\Infrastructure\PayloadCipher($config),new Sgpm\Infrastructure\CorporateEmailAdapter($config,new Sgpm\Infrastructure\LibCurlModel(),$profile));
    $count=0;
    do { $worked=$worker->once(); if ($worked) { ++$count; } } while ($worked && !in_array('--once',$argv,true) && $count<100);
    echo $count." notificaciones procesadas localmente.\n";
} catch (Throwable $error) { fwrite(STDERR,"No fue posible procesar notificaciones. Revisa el entorno local.\n"); exit(1); }
