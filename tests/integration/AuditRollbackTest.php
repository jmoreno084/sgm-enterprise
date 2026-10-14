<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext();
    foreach (array('UPDATE sgpm.auditoria SET accion=accion WHERE false','DELETE FROM sgpm.auditoria WHERE false','TRUNCATE sgpm.auditoria',
        "INSERT INTO sgpm.auditoria(accion,entidad,ip,request_id) VALUES('DELETE','usuario','127.0.0.1','x')") as $sql) {
        try { $f->db->execute($sql); throw new RuntimeException('Runtime altero bitacora.'); }
        catch (PDOException $error) { $assert($error->getCode()==='42501','Bitacora protegida por privilegios.'); }
    }
    $values=require dirname(__DIR__,2).'/config/config.php'; $values['db_user']=getenv('SGPM_MIGRATION_DB_USER'); $values['db_password']=getenv('SGPM_MIGRATION_DB_PASSWORD');
    $owner=new Sgpm\Infrastructure\Database(new Sgpm\Infrastructure\Configuration($values)); $owner->execute('SET ROLE sgpm_owner');
    $owner->execute("CREATE FUNCTION sgpm.fixture_audit_rollback() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Fallo fixture' USING ERRCODE='23514'; END; $$");
    $nit='AUDIT-'.bin2hex(random_bytes(5));
    try {
        $owner->execute('CREATE TRIGGER fixture_audit_rollback BEFORE INSERT ON sgpm.auditoria FOR EACH ROW EXECUTE PROCEDURE sgpm.fixture_audit_rollback()');
        try {
            (new Sgpm\Modules\Empresas\CompanyService($f->db))->save(0,array('nit'=>$nit,'razon_social'=>'Rollback','correo'=>'rollback@example.invalid','ciudad'=>'Bogotá','departamento'=>'Cundinamarca'),$f->admin);
            throw new RuntimeException('No hubo rollback de empresa.');
        } catch (PDOException $error) { $assert($error->getCode()==='23514','Fallo de auditoria propagado.'); }
        $assert((int)$f->db->execute('SELECT count(*) FROM sgpm.empresa WHERE nit=:nit',array('nit'=>$nit))->fetchColumn()===0,'Mutacion maestra revertida.');
    } finally { $owner->execute('DROP TRIGGER fixture_audit_rollback ON sgpm.auditoria'); $owner->execute('DROP FUNCTION sgpm.fixture_audit_rollback()'); }
};
