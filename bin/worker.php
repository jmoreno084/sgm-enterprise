<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    $onlyJob=0;
    foreach ($argv as $argument) {
        if (preg_match('/^--job-id=([1-9][0-9]*)$/D',$argument,$match)) { $onlyJob=(int)$match[1]; }
        elseif (strpos($argument,'--job-id=')===0) { throw new InvalidArgumentException('Identificador de job inválido.'); }
    }
    $config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__).'/config/config.php');
    if ($config->get('env')==='production' && !in_array('--production',$argv,true)) { throw new RuntimeException('Produccion requiere opcion explicita.'); }
    if (in_array('--env=test',$argv,true)) { $config->assertTestDatabase(); }
    $db=new Sgpm\Infrastructure\Database($config);
    $exports=new Sgpm\Jobs\ExportWorker($db,new Sgpm\Modules\Reportes\ReportNotifications(new Sgpm\Jobs\NotificationRepository($db,new Sgpm\Infrastructure\PayloadCipher($config)),$config->get('base_url')));
    $exportCount=0;
    do { $exported=$exports->once($onlyJob); if ($exported) { ++$exportCount; } } while ($exported && $onlyJob===0 && !in_array('--once',$argv,true) && $exportCount<100);
    $profileFile=dirname(__DIR__).'/config/email-response.php';
    $profile=is_file($profileFile)?require $profileFile:array();
    (new Sgpm\Jobs\AuditAlertProducer($db,new Sgpm\Jobs\NotificationRepository($db,new Sgpm\Infrastructure\PayloadCipher($config))))->once();
    $worker=new Sgpm\Jobs\NotificationWorker($db,new Sgpm\Infrastructure\PayloadCipher($config),new Sgpm\Infrastructure\CorporateEmailAdapter($config,new Sgpm\Infrastructure\LibCurlModel(),$profile));
    $count=0;
    do { $worked=$worker->once(); if ($worked) { ++$count; } } while ($worked && !in_array('--once',$argv,true) && $count<100);
    echo $count." notificaciones procesadas localmente.\n";
} catch (Throwable $error) { fwrite(STDERR,"No fue posible procesar notificaciones. Revisa el entorno local.\n"); exit(1); }
