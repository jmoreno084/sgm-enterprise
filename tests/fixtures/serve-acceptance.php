<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/bootstrap.php';
require __DIR__.'/LocalApache.php';

if (PHP_SAPI!=='cli' || getenv('SGPM_ENV')!=='test') { http_response_code(404); exit(1); }
$server=new SgpmLocalApache();
try {
    $server->start(true);
    file_put_contents('/tmp/sgpm-acceptance.json',json_encode(array('url'=>$server->url(),'ca'=>$server->ca())));
    echo "Entorno HTTPS de pruebas disponible.\n";
    while (true) { sleep(1); }
} finally { $server->close(); }
