<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleHttpsClient.php';
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $server=new SgpmLocalApache(); $clients=array(); $image=null; $large=null;
    try {
        $server->start();
        foreach (array('admin','geologo','gerente') as $role) { $client=new SgpmModuleHttpsClient($server); $client->login($role.'@example.invalid',getenv('SGPM_FIXTURE_PASSWORD')); $clients[$role]=$client; }
        $admin=$clients['admin']; $geo=$clients['geologo']; $manager=$clients['gerente'];
        $assert($geo->call('GET','/api/v1/empresas')['json']['data']['total']===1,'Lista empresarial no filtra B.');
        $assert($geo->call('GET','/api/v1/empresas/'.$f->b)['status']===404,'Empresa ajena 404.');
        $assert($manager->call('POST','/api/v1/empresas',array())['status']===403,'Solo admin crea empresa.');
        $assert($admin->call('POST','/api/v1/empresas',array(),false,false)['status']===403,'Mutacion empresarial exige CSRF.');
        $company=$admin->call('POST','/api/v1/empresas',array('nit'=>'HTTP-'.bin2hex(random_bytes(5)),'razon_social'=>'Minería <script>','correo'=>'http@example.invalid','ciudad'=>'Bogotá','departamento'=>'Cundinamarca'));
        $assert($company['status']===201 && strpos($company['headers'],'Location: /api/v1/empresas/')!==false,'Empresa 201 Location.');
        $id=$company['json']['data']['id'];
        $image=tempnam(sys_get_temp_dir(),'sgpm-logo-');
        file_put_contents($image,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS0AAAAASUVORK5CYII='));
        $upload=array('version'=>'1','archivo'=>new CURLFile($image,'image/png','../../logo.php'));
        $logo=$admin->call('POST','/api/v1/empresas/'.$id.'/logo',$upload,true);
        $assert($logo['status']===200 && $logo['json']['data']['logo_url']==='/api/v1/empresas/'.$id.'/logo','Logo privado con ruta autorizada.');
        $received=$admin->call('GET','/api/v1/empresas/'.$id.'/logo');
        $assert($received['status']===200 && $received['text']===file_get_contents($image) && stripos($received['headers'],'Content-Type: image/png')!==false,'Logo servido como imagen verificada.');
        $assert($geo->call('GET','/api/v1/empresas/'.$id.'/logo')['status']===404,'Logo de empresa ajena 404.');
        $assert($admin->call('POST','/api/v1/empresas/'.$id.'/logo',$upload,true)['status']===409,'Logo obsoleto 409.');
        file_put_contents($image,'<?php echo "not an image";');
        $assert($admin->call('POST','/api/v1/empresas/'.$id.'/logo',array('version'=>'2','archivo'=>new CURLFile($image,'image/png','fake.png')),true)['status']===415,'Contenido falso rechazado.');
        $large=tempnam(sys_get_temp_dir(),'sgpm-large-logo-'); file_put_contents($large,str_repeat('X',6*1024*1024));
        $assert($admin->call('POST','/api/v1/empresas/'.$id.'/logo',array('version'=>'2','archivo'=>new CURLFile($large,'image/png','large.png')),true)['status']===413,'Logo sobre limite 413.');
        $project=$geo->call('POST','/api/v1/empresas/'.$f->a.'/proyectos',array('nombre'=>'Proyecto HTTP Ñ','mineral_principal'=>'Oro'));
        $assert($project['status']===201,'Geologo crea proyecto propio.'); $pid=$project['json']['data']['id'];
        $assert($manager->call('PUT','/api/v1/proyectos/'.$pid,array('version'=>1,'nombre'=>'No'))['status']===403,'Gerente no edita proyectos.');
        $assert($geo->call('POST','/api/v1/empresas/'.$f->b.'/proyectos',array('nombre'=>'No','mineral_principal'=>'Oro'))['status']===404,'Geologo no crea en B.');
        foreach (array('/empresas','/empresas/'.$f->a.'/proyectos','/proyectos/'.$pid) as $path) { $assert($geo->call('GET',$path)['status']===200,'Pantalla autorizada '.$path); }
        $code=$f->db->execute('SELECT codigo_expediente FROM public.geo_titulos_mineros ORDER BY codigo_expediente LIMIT 1')->fetchColumn();
        $body=array('codigo_expediente'=>$code,'tipo_expediente'=>'TITULO','filiacion'=>'PROPIO');
        $associated=$geo->call('POST','/api/v1/proyectos/'.$pid.'/expedientes',$body);
        $assert($associated['status']===201,'Asociacion HTTP 201.');
        $assert($geo->call('POST','/api/v1/proyectos/'.$pid.'/expedientes',$body)['status']===409,'RN12 HTTP 409.');
        $list=$geo->call('GET','/api/v1/proyectos/'.$pid.'/expedientes');
        $assert($list['json']['data']['total']===1 && $list['json']['data']['items'][0]['fuente_disponible'],'Lista expediente con fuente actual.');
        $file=$associated['json']['data'];
        $assert($geo->call('DELETE','/api/v1/proyectos/'.$pid.'/expedientes/'.$file['id'],array('version'=>$file['version']))['status']===204,'Desvinculacion HTTP 204.');
        foreach ($clients as $client) { $client->call('DELETE','/api/v1/sesion'); }
    } finally {
        foreach ($clients as $client) { $client->close(); }
        foreach (array($image,$large) as $file) { if ($file && is_file($file)) { unlink($file); } }
        $server->close();
    }
};
