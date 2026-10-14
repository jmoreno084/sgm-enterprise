<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleHttpsClient.php';
return function ($assert) {
    $server=new SgpmLocalApache(); $client=null;
    try {
        $server->start(); $client=new SgpmModuleHttpsClient($server);
        $assert($client->call('GET','/api/v1/catastro')['status']===401,'Catastro exige sesion.');
        $client->login('geologo@example.invalid',getenv('SGPM_FIXTURE_PASSWORD'));
        $result=$client->call('GET','/api/v1/catastro?codigo=T-A');
        $assert($result['status']===200 && $result['json']['data']['total']===1,'Consulta catastro HTTP.');
        $detail=$client->call('GET','/api/v1/catastro/TITULO/T-A');
        $assert($detail['status']===200 && count($detail['json']['data']['atributos'])===30,'Ficha HTTP completa.');
        $page=$client->call('GET','/catastro/TITULO/T-A');
        $assert($page['status']===200 && substr_count($page['text'],'<dt ')===30 && strpos($page['text'],'No disponible (NULL)')!==false,'Ficha SSR 30 atributos y NULL.');
        $assert($client->call('GET','/catastro')['status']===200,'Buscador SSR disponible.');
        foreach (array('POST','PUT','DELETE') as $method) {
            foreach (array('/api/v1/catastro','/api/v1/catastro/TITULO/T-A','/api/v1/catastro/validar','/api/v1/catastro/reparar') as $path) {
                $assert($client->call($method,$path)['status']===404,'Sin superficie de escritura/topologia '.$path);
            }
        }
    } finally { if ($client) { $client->close(); } $server->close(); }
};
