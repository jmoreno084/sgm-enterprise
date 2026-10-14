<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleHttpsClient.php';
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $f->project(); $f->project($f->b); $server=new SgpmLocalApache(); $clients=array();
    try {
        $server->start();
        foreach (array('admin','gerente','inversionista','geologo') as $role) { $c=new SgpmModuleHttpsClient($server); $c->login($role.'@example.invalid',getenv('SGPM_FIXTURE_PASSWORD')); $clients[$role]=$c; }
        foreach (array('gerente','inversionista') as $role) {
            $c=$clients[$role]; $list=$c->call('GET','/api/v1/empresas/'.$f->a.'/auditoria'); $assert($list['status']===200,'X consulta A.');
            foreach ($list['json']['data']['items'] as $row) { $assert($row['empresa_id_contexto']===$f->a,'Solo A por HTTP.'); }
            $assert($c->call('GET','/api/v1/empresas/'.$f->b.'/auditoria')['status']===404,'B404 por HTTP.');
            $assert($c->call('GET','/api/v1/auditoria')['status']===403,'Global reservado A.');
            $assert($c->call('GET','/empresas/'.$f->a.'/auditoria')['status']===200,'UI auditoria empresarial.');
        }
        $geo=$clients['geologo']; $assert($geo->call('GET','/api/v1/empresas/'.$f->a.'/auditoria')['status']===403,'Geologo auditoria403.');
        $assert($geo->call('GET','/empresas/'.$f->a.'/auditoria')['status']===403,'Geologo UI auditoria403.');
        $admin=$clients['admin']; $list=$admin->call('GET','/api/v1/auditoria'); $assert($list['status']===200 && $list['json']['data']['total']>0,'Global A por HTTP.');
        $event=$list['json']['data']['items'][0];
        $assert($admin->call('GET','/auditoria/'.$event['id'])['status']===200,'Ficha antes/despues.');
        foreach (array('POST','PUT','DELETE') as $method) { $assert($admin->call($method,'/api/v1/auditoria/'.$event['id'])['status']===404,'Sin endpoint de mutacion auditoria.'); }
        $values=require dirname(__DIR__,2).'/config/config.php'; $values['db_user']=getenv('SGPM_MIGRATION_DB_USER'); $values['db_password']=getenv('SGPM_MIGRATION_DB_PASSWORD');
        $owner=new Sgpm\Infrastructure\Database(new Sgpm\Infrastructure\Configuration($values)); $owner->execute('SET ROLE sgpm_owner');
        $owner->execute("CREATE FUNCTION sgpm.fixture_audit_http_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Fallo fixture' USING ERRCODE='23514'; END; $$");
        try {
            $owner->execute('CREATE TRIGGER fixture_audit_http_failure BEFORE INSERT ON sgpm.auditoria FOR EACH ROW EXECUTE PROCEDURE sgpm.fixture_audit_http_failure()');
            $failed=$admin->call('POST','/api/v1/empresas',array('nit'=>'FAIL-'.bin2hex(random_bytes(5)),'razon_social'=>'Rollback HTTP','correo'=>'audit@example.invalid','ciudad'=>'Bogotá','departamento'=>'Cundinamarca'));
            $assert($failed['status']===500 && isset($failed['json']['meta']['request_id']) && strpos($failed['text'],'SQLSTATE')===false && strpos($failed['text'],'Fallo fixture')===false,'Error interno500 amigable sin trazas.');
            $log=dirname(__DIR__,2).'/logs/sgpm-'.gmdate('Y-m-d').'.log';
            $assert(is_file($log),'Incidente genera log diario.'); $text=file_get_contents($log);
            $assert(strpos($text,$failed['json']['meta']['request_id'])!==false && strpos($text,getenv('SGPM_FIXTURE_PASSWORD'))===false && strpos($text,'SQLSTATE')===false,'Log correlacionado y saneado.');
        } finally { $owner->execute('DROP TRIGGER fixture_audit_http_failure ON sgpm.auditoria'); $owner->execute('DROP FUNCTION sgpm.fixture_audit_http_failure()'); }
    } finally { foreach ($clients as $c) { $c->close(); } $server->close(); }
};
