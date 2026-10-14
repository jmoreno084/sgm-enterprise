<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
require_once dirname(__DIR__).'/fixtures/ModuleHttpsClient.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $server=new SgpmLocalApache(); $client=null;
    try {
        $server->start(); $client=new SgpmModuleHttpsClient($server); $client->login('admin@example.invalid',getenv('SGPM_FIXTURE_PASSWORD'));
        foreach (array('/config/config.php','/storage/acceptance/local.json','/logs/application.log','/bin/worker.php','/vendor/tfpdf/tfpdf.php','/.git/config','/../config/config.php','/%2e%2e/config/config.php') as $path) {
            $response=$client->call('GET',$path); $assert(in_array($response['status'],array(400,403,404),true),'Directorio privado inaccesible: '.$path);
            $assert(strpos($response['text'],'SGPM_DB_PASSWORD')===false && strpos($response['text'],'declare(strict_types')===false,'Sin fuente/configuración expuesta.');
        }
        $company=$client->call('GET','/api/v1/empresas?razon_social='.rawurlencode("' OR 1=1 --"));
        $cadastre=$client->call('GET','/api/v1/catastro?codigo='.rawurlencode("' OR 1=1 --"));
        $assert($company['status']===200 && $company['json']['data']['total']===0 && $cadastre['status']===200 && $cadastre['json']['data']['total']===0,'SQLi se trata como texto literal.');
        $payload=array('nombre'=>'<script>window.sgpmInjected=1</script> Ñ','mineral_principal'=>'Oro');
        $project=$client->call('POST','/api/v1/empresas/'.$f->a.'/proyectos',$payload)['json']['data'];
        $html=$client->call('GET','/proyectos/'.$project['id']);
        $assert(strpos($html['text'],'<script>window.sgpmInjected')===false && strpos($html['text'],'&lt;script&gt;')!==false,'SSR escapa atributos externos.');
        $assert($client->call('POST','/api/v1/empresas/'.$f->a.'/proyectos',$payload,false,false)['status']===403,'CSRF sin token rechazado.');
        $error=$client->call('GET','/api/v1/proyectos/999999999');
        $assert($error['status']===404 && isset($error['json']['meta']['request_id']) && strpos($error['text'],'PDOException')===false && strpos($error['text'],'/workspace')===false,'Errores saneados con request_id.');
        foreach (array('Strict-Transport-Security:','X-Content-Type-Options: nosniff','X-Frame-Options: DENY') as $header) { $assert(stripos($html['headers'],$header)!==false,'Header HTTPS '.$header); }
        $assert(stripos($html['headers'],'Content-Security-Policy:')!==false,'CSP presente.');
    } finally { if ($client) { $client->close(); } $server->close(); }
};
