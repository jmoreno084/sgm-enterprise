<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleHttpsClient.php';
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $p=$f->project(); $other=$f->project($f->b); $server=new SgpmLocalApache(); $clients=array();
    try {
        $server->start();
        foreach (array('admin','geologo','gerente') as $role) { $c=new SgpmModuleHttpsClient($server); $c->login($role.'@example.invalid',getenv('SGPM_FIXTURE_PASSWORD')); $clients[$role]=$c; }
        $geo=$clients['geologo']; $admin=$clients['admin']; $manager=$clients['gerente']; $api='/api/v1/proyectos/'.$p['id'].'/vecinas';
        $nit='HTTP-N-'.bin2hex(random_bytes(5));
        $created=$geo->call('POST',$api,array('nueva_identidad'=>array('nombre'=>'Vecina HTTP Ñ','nit'=>$nit)));
        $assert($created['status']===201,'Geologo crea identidad/vinculo atomicos.'); $n=$created['json']['data'];
        $assert($geo->call('GET','/api/v1/vecinas')['status']===403,'Catalogo global reservado A.');
        $assert($geo->call('PUT','/api/v1/vecinas/'.$n['empresa_vecina_id'],array('version'=>1,'nombre'=>'No'))['status']===403,'Geologo no cambia identidad compartida.');
        $resolved=$geo->call('POST',$api.'/resolver',array('nit'=>$nit));
        $assert($resolved['status']===200 && count($resolved['json']['data'])===3,'Resolver NIT exacto sin vinculos privados.');
        $assert($geo->call('POST',$api,array('empresa_vecina_id'=>$n['empresa_vecina_id']))['status']===409,'Vinculo repetido409.');
        $assert($manager->call('POST',$api,array('empresa_vecina_id'=>$n['empresa_vecina_id']))['status']===403,'Gerente no vincula vecinas.');
        $assert($geo->call('GET','/api/v1/proyectos/'.$other['id'].'/vecinas')['status']===404,'No revela vinculos B.');
        $assert($geo->call('GET','/proyectos/'.$p['id'].'/vecinas')['status']===200 && $admin->call('GET','/vecinas')['status']===200,'Pantallas de proyecto y catalogo.');
        $fileApi='/api/v1/proyectos/'.$p['id'].'/expedientes';
        $data=array('codigo_expediente'=>'T-A','tipo_expediente'=>'TITULO','filiacion'=>'VECINO','empresa_vecina_id'=>$n['empresa_vecina_id']);
        $assert($geo->call('POST',$fileApi,$data)['status']===201,'Asocia expediente VECINO.');
        $data['filiacion']='PROPIO'; $data['empresa_vecina_id']=null;
        $assert($geo->call('POST',$fileApi,$data)['status']===409,'RN12 vecino/propio no se elude.');
        $assert($geo->call('DELETE',$api.'/'.$n['empresa_vecina_id'],array('version'=>1))['status']===409,'Vecina con expediente no se desvincula.');
        $assert($admin->call('PUT','/api/v1/vecinas/'.$n['empresa_vecina_id'],array('version'=>1,'nombre'=>'Identidad editada'))['status']===200,'A edita identidad global.');
        $list=$geo->call('GET',$api)['json']['data']['items']; $assert($list[0]['nombre']==='Identidad editada' && $list[0]['color_hex']==='#8a2be2','Hereda identidad editada y conserva estilo del vinculo.');
    } finally { foreach ($clients as $c) { $c->close(); } $server->close(); }
};
