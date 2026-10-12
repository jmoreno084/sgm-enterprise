<?php
declare(strict_types=1);

return function ($assert) {
    if (PHP_VERSION !== '7.0.33') {
        throw new RuntimeException('Entorno pendiente: se requiere PHP 7.0.33; disponible ' . PHP_VERSION . '.');
    }
    if (!extension_loaded('pdo_pgsql')) { throw new RuntimeException('Entorno pendiente: falta pdo_pgsql.'); }
    $file = dirname(__DIR__, 2) . '/config/config.php';
    if (!is_file($file)) { throw new RuntimeException('Entorno pendiente: falta configuración de aceptación.'); }
    $values = require $file;
    $config = new Sgpm\Infrastructure\Configuration($values);
    $config->assertTestDatabase();
    $assert($config->get('db_user') === 'sgpm_user', 'Prueba requiere rol runtime sgpm_user.');
    $db = new Sgpm\Infrastructure\Database($config);
    $assert($db->execute('SHOW server_version')->fetchColumn() === '9.5.24', 'PostgreSQL 9.5.24 requerido.');
    $assert(strpos($db->execute('SELECT public.postgis_lib_version()')->fetchColumn(), '2.2.') === 0, 'PostGIS 2.2.x requerido.');
    $assert($db->execute('SELECT current_user')->fetchColumn() === 'sgpm_user', 'Rol runtime correcto.');
    $assert(!$db->execute("SELECT pg_has_role(current_user,'sgpm_owner','MEMBER')")->fetchColumn(), 'Runtime no pertenece al propietario.');
    foreach (array('INSERT','UPDATE','DELETE','TRUNCATE','TRIGGER') as $privilege) {
        $assert(!$db->execute("SELECT has_table_privilege(current_user,'sgpm.auditoria',:privilege)", array('privilege'=>$privilege))->fetchColumn(), 'Auditoría sin privilegio '.$privilege);
    }
    foreach (array('geo_titulos_mineros','geo_solicitudes_mineras') as $source) {
        $table = 'public.' . $source;
        $assert((bool)$db->execute('SELECT has_table_privilege(current_user,:table,:privilege)',array('table'=>$table,'privilege'=>'SELECT'))->fetchColumn(), 'Fuente legible.');
        foreach (array('INSERT','UPDATE','DELETE','TRUNCATE','TRIGGER') as $privilege) {
            $assert(!$db->execute('SELECT has_table_privilege(current_user,:table,:privilege)',array('table'=>$table,'privilege'=>$privilege))->fetchColumn(), 'Fuente solo SELECT: '.$privilege);
        }
    }
    $assert(!$db->execute("SELECT has_schema_privilege(current_user,'sgpm','CREATE')")->fetchColumn(), 'Sin DDL en esquema.');
    $nit = 'INTEGRITY-' . bin2hex(random_bytes(6));
    $insert = function ($database) use ($nit) {
        return (int)$database->execute("INSERT INTO sgpm.empresa(nit,razon_social,correo,ciudad,departamento) VALUES(:nit,'Prueba rollback','test@example.invalid','Bogota','Cundinamarca') RETURNING id", array('nit'=>$nit))->fetchColumn();
    };
    // La ausencia de contexto de auditoría debe impedir la mutación completa.
    try {
        $insert($db);
        throw new RuntimeException('Se admitió escritura sin contexto.');
    } catch (PDOException $error) {
        $assert($error->getCode() === '23514', 'Escritura sin contexto rechazada.');
    }
    $assert((int)$db->execute('SELECT count(*) FROM sgpm.empresa WHERE nit=:nit', array('nit'=>$nit))->fetchColumn() === 0, 'Mutación sin contexto revertida.');
    $transaction = new Sgpm\Infrastructure\Transaction($db);
    $context = array('usuario_id'=>null,'sistema'=>true,'ip'=>'127.0.0.1','request_id'=>bin2hex(random_bytes(16)));
    try {
        $transaction->run($context, function ($database) use ($insert, $assert, $context) {
            $id = $insert($database);
            $audit = $database->execute("SELECT * FROM sgpm.auditoria WHERE request_id=:request AND entidad='empresa' AND entidad_id=:id",array('request'=>$context['request_id'],'id'=>$id))->fetch();
            $assert($audit && (int)$audit['empresa_id_contexto'] === $id && $audit['valores_anteriores'] === null, 'Auditoría y contexto atómicos.');
            throw new RuntimeException('ROLLBACK_FIXTURE');
        });
    } catch (RuntimeException $error) { $assert($error->getMessage() === 'ROLLBACK_FIXTURE', 'Rollback intencional.'); }
    $assert((int)$db->execute('SELECT count(*) FROM sgpm.empresa WHERE nit=:nit',array('nit'=>$nit))->fetchColumn() === 0, 'Rollback de entidad.');
    $assert((int)$db->execute('SELECT count(*) FROM sgpm.auditoria WHERE request_id=:request',array('request'=>$context['request_id']))->fetchColumn() === 0, 'Rollback de auditoría.');
    foreach (array('UPDATE sgpm.auditoria SET accion=accion WHERE false', 'DELETE FROM sgpm.auditoria WHERE false', 'TRUNCATE sgpm.auditoria', 'ALTER TABLE sgpm.auditoria ADD COLUMN forbidden INTEGER', 'INSERT INTO sgpm.auditoria(accion,entidad,ip,request_id) VALUES(\'LOGIN\',\'usuario\',\'127.0.0.1\',\'forbidden\')', 'UPDATE public.geo_titulos_mineros SET codigo_expediente=codigo_expediente WHERE false') as $sql) {
        try { $db->execute($sql); throw new RuntimeException('Privilegio no denegado.'); }
        catch (PDOException $error) { $assert($error->getCode() === '42501', 'Mutación/DDL protegidos.'); }
    }
    // Forzar un fallo del trigger de auditoría en la base aislada y comprobar rollback.
    $values['db_user'] = getenv('SGPM_MIGRATION_DB_USER') ?: '';
    $values['db_password'] = getenv('SGPM_MIGRATION_DB_PASSWORD') ?: '';
    $assert($values['db_user'] !== '' && $values['db_user'] !== 'sgpm_user', 'Cuenta de pruebas migradora separada requerida.');
    $owner = new Sgpm\Infrastructure\Database(new Sgpm\Infrastructure\Configuration($values));
    $owner->execute('SET ROLE sgpm_owner');
    $owner->execute("CREATE FUNCTION sgpm.fixture_audit_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Fallo fixture' USING ERRCODE='23514'; END; $$");
    try {
        $owner->execute('CREATE TRIGGER fixture_audit_failure BEFORE INSERT ON sgpm.auditoria FOR EACH ROW EXECUTE PROCEDURE sgpm.fixture_audit_failure()');
        try {
            $transaction->run($context, $insert);
            throw new RuntimeException('No se propagó el fallo de auditoría.');
        } catch (PDOException $error) { $assert($error->getCode() === '23514', 'Fallo de auditoría propagado.'); }
        $assert((int)$db->execute('SELECT count(*) FROM sgpm.empresa WHERE nit=:nit',array('nit'=>$nit))->fetchColumn() === 0, 'Mutación revertida si falla auditoría.');
    } finally {
        $owner->execute('DROP TRIGGER IF EXISTS fixture_audit_failure ON sgpm.auditoria');
        $owner->execute('DROP FUNCTION sgpm.fixture_audit_failure()');
    }
};
