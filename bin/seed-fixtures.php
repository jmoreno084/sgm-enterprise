<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
try {
    if (PHP_VERSION !== '7.0.33') { throw new RuntimeException('Se requiere PHP7.0.33.'); }
    $values = require dirname(__DIR__) . '/config/config.php';
    $external = in_array('--external', $argv, true);
    if ($external) {
        $values['db_user'] = getenv('SGPM_FIXTURE_DB_USER') ?: '';
        $values['db_password'] = getenv('SGPM_FIXTURE_DB_PASSWORD') ?: '';
    }
    $config = new Sgpm\Infrastructure\Configuration($values);
    $config->assertTestDatabase();
    if ($external) {
        if ($config->get('db_user') === 'sgpm_user') { throw new RuntimeException('Usa propietario fixture separado.'); }
        $db = new Sgpm\Infrastructure\Database($config);
        $db->execute('SET ROLE sgpm_fixture_owner');
        $db->pdo()->beginTransaction();
        try {
            Sgpm\Infrastructure\SqlScript::run($db, file_get_contents(dirname(__DIR__) . '/tests/fixtures/external-cadastre.sql'));
            $db->pdo()->commit();
        } catch (Throwable $error) {
            $db->pdo()->rollBack();
            throw $error;
        }
        echo "Fuentes fixture creadas en base aislada.\n";
        exit(0);
    }
    if ($config->get('db_user') !== 'sgpm_user') { throw new RuntimeException('Las fixtures internas usan runtime.'); }
    $database = new Sgpm\Infrastructure\Database($config);
    $fixtures = require dirname(__DIR__) . '/tests/fixtures/acceptance.php';
    $password = getenv('SGPM_FIXTURE_PASSWORD') ?: '';
    if (mb_strlen($password,'UTF-8') < 12 || strlen($password) > 72) { throw new RuntimeException('Configura clave fixture temporal.'); }
    (new Sgpm\Infrastructure\Transaction($database))->run(array('usuario_id'=>null,'sistema'=>true,
        'ip'=>'127.0.0.1','request_id'=>bin2hex(random_bytes(16))), function ($db) use ($fixtures,$password) {
        if ((int)$db->execute('SELECT count(*) FROM sgpm.usuario')->fetchColumn() !== 0) {
            throw new RuntimeException('Las fixtures requieren tablas internas vacías; no se borran datos.');
        }
        $companies=array();
        foreach ($fixtures['companies'] as $company) {
            $companies[]=(int)$db->execute('INSERT INTO sgpm.empresa(nit,razon_social,correo,ciudad,departamento) VALUES(:nit,:razon_social,:correo,:ciudad,:departamento) RETURNING id',$company)->fetchColumn();
        }
        foreach ($fixtures['users'] as $user) {
            $id=(int)$db->execute('INSERT INTO sgpm.usuario(correo,password_hash,numero_documento,nombre,es_administrador) VALUES(:correo,:hash,:documento,:nombre,:admin) RETURNING id',
                array('correo'=>$user['correo'],'hash'=>password_hash($password,PASSWORD_BCRYPT,array('cost'=>10)),
                    'documento'=>$user['numero_documento'],'nombre'=>$user['nombre'],'admin'=>!empty($user['admin'])))->fetchColumn();
            if (isset($user['rol'])) {
                $db->execute('INSERT INTO sgpm.empresa_usuarios(empresa_id,usuario_id,rol) VALUES(:empresa,:usuario,:rol)',
                    array('empresa'=>$companies[0],'usuario'=>$id,'rol'=>$user['rol']));
            }
        }
    });
    echo "Fixtures internas A/B creadas; no se modificaron fuentes.\n";
} catch (Throwable $error) {
    fwrite(STDERR,"Fixtures no aplicadas. Revisa aislamiento, entorno y configuración.\n");
    exit(1);
}

