<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    $config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__).'/config/config.php');
    if ($config->get('env')==='production' && !in_array('--production',$argv,true)) { throw new RuntimeException('Producción requiere opción explícita.'); }
    if (in_array('--env=test',$argv,true)) { $config->assertTestDatabase(); }
    $db=new Sgpm\Infrastructure\Database($config);
    (new Sgpm\Jobs\MaintenanceWorker($db))->once();
    (new Sgpm\Jobs\AuditAlertProducer($db,new Sgpm\Jobs\NotificationRepository($db,new Sgpm\Infrastructure\PayloadCipher($config))))->once();
    echo (new Sgpm\Jobs\ReportScheduler($db))->once()." reportes programados localmente.\n";
} catch (Throwable $error) { fwrite(STDERR,"No fue posible ejecutar el planificador. Revisa el entorno local.\n"); exit(1); }
