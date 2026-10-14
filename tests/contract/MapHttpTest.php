<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
require_once dirname(__DIR__).'/fixtures/ModuleHttpsClient.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $p=$f->project(); $q=$f->project($f->b);
    $file=(new Sgpm\Modules\Proyectos\ExpedienteService($f->db))->save($p['id'],0,array('codigo_expediente'=>'T-A','tipo_expediente'=>'TITULO','filiacion'=>'PROPIO'),$f->admin);
    $values=require dirname(__DIR__,2).'/config/config.php'; $values['db_user']=getenv('SGPM_FIXTURE_DB_USER'); $values['db_password']=getenv('SGPM_FIXTURE_DB_PASSWORD');
    $owner=new Sgpm\Infrastructure\Database(new Sgpm\Infrastructure\Configuration($values)); $owner->execute('SET ROLE sgpm_fixture_owner');
    $original=$owner->execute("SELECT * FROM public.geo_titulos_mineros WHERE codigo_expediente='T-A'")->fetch(); $deleted=false;
    $server=new SgpmLocalApache(); $client=null; $temp=new Sgpm\Infrastructure\TemporaryFiles();
    try {
        $server->start(); $client=new SgpmModuleHttpsClient($server); $client->login('geologo@example.invalid',getenv('SGPM_FIXTURE_PASSWORD'));
        $api='/api/v1/proyectos/'.$p['id'];
        $map=$client->call('GET',$api.'/mapa'); $assert($map['status']===200 && count($map['json']['data']['features'])===1,'Mapa HTTPS con geometria original.');
        $assert($client->call('GET',$api.'/mapa?bbox=0,0,1,1')['json']['data']['features']===array(),'bbox filtra sin alterar geometria.');
        $assert($client->call('GET',$api.'/mapa?bbox=bad')['status']===400,'bbox invalido400.');
        $assert($client->call('GET','/api/v1/proyectos/'.$q['id'].'/mapa')['status']===404,'Mapa B404.');
        $assert($client->call('GET','/proyectos/'.$p['id'].'/mapa')['status']===200,'Visor SSR autorizado.');
        $owner->execute("UPDATE public.geo_titulos_mineros SET area_ha=0 WHERE codigo_expediente='T-A'");
        $props=$client->call('GET',$api.'/mapa')['json']['data']['features'][0]['properties'];
        $assert($props['area_ha']==0 && $props['area_origen']==='OFICIAL','Cero oficial no se sustituye por calculo.');
        $owner->execute("DELETE FROM public.geo_titulos_mineros WHERE codigo_expediente='T-A'"); $deleted=true;
        $map=$client->call('GET',$api.'/mapa'); $assert($map['status']===200 && !$map['json']['data']['features'] && count($map['json']['meta']['fuentes_no_disponibles'])===1,'Fuente retirada enumerada, sin geometria ficticia.');
        $detail=$client->call('GET',$api.'/identify/'.$file['id'])['json']['data'];
        $assert(!$detail['fuente_disponible'] && count($detail['atributos'])===30 && $detail['area_origen']==='NO_DISPONIBLE','Identify historico sin fuente.');
        $upload=$temp->path('layer.geojson'); file_put_contents($upload,'{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[-74.1,4.6]},"properties":{"nombre":"Ñ"}}]}');
        $body=array('archivo'=>new CURLFile($upload,'application/geo+json','layer.geojson'));
        $assert($client->call('POST',$api.'/capas/importar',$body,true,false)['status']===403,'Importacion exigeCSRF.');
        $assert($client->call('POST',$api.'/capas/importar',$body,true)['status']===200,'Importacion C200.');
        $assert($client->call('POST','/api/v1/proyectos/'.$q['id'].'/capas/importar',$body,true)['status']===404,'Importacion B404.');
    } finally {
        if ($deleted) { $columns=array_keys($original); $owner->execute('INSERT INTO public.geo_titulos_mineros('.implode(',',$columns).') VALUES(:'.implode(',:',$columns).')',$original); }
        else { $owner->execute("UPDATE public.geo_titulos_mineros SET area_ha=:area WHERE codigo_expediente='T-A'",array('area'=>$original['area_ha'])); }
        if ($client) { $client->close(); } $server->close(); $temp->close();
    }
};
