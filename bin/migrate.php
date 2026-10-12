<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
try {
    if (PHP_VERSION !== '7.0.33') { throw new RuntimeException('Migración requiere PHP 7.0.33.'); }
    $root = dirname(__DIR__);
    $file = $root . '/config/config.php';
    if (!is_file($file)) { throw new RuntimeException('Configura config/config.php fuera de public.'); }
    $values = require $file;
    $values['db_user'] = getenv('SGPM_MIGRATION_DB_USER') ?: '';
    $values['db_password'] = getenv('SGPM_MIGRATION_DB_PASSWORD') ?: '';
    if ($values['db_user'] === '' || $values['db_user'] === 'sgpm_user') {
        throw new RuntimeException('Se requiere una cuenta migradora separada del runtime.');
    }
    $config = new Sgpm\Infrastructure\Configuration($values);
    if (in_array('--env=test', $argv, true)) { $config->assertTestDatabase(); }
    if ($config->get('env') === 'production' && !in_array('--production', $argv, true)) {
        throw new RuntimeException('Producción requiere la opción explícita --production.');
    }
    $db = new Sgpm\Infrastructure\Database($config);
    $version = $db->execute('SHOW server_version')->fetchColumn();
    $postgis = $db->execute('SELECT public.postgis_lib_version()')->fetchColumn();
    if ($version !== '9.5.24' || strpos($postgis, '2.2.') !== 0) {
        throw new RuntimeException('Se requiere PostgreSQL 9.5.24 y PostGIS 2.2.x.');
    }
    $db->execute('SET ROLE sgpm_owner');
    $db->execute('SELECT pg_advisory_lock(534750, 1)');
    try {
        $db->execute('CREATE SCHEMA IF NOT EXISTS sgpm');
        $db->execute('CREATE TABLE IF NOT EXISTS sgpm.schema_migrations (nombre VARCHAR(255) PRIMARY KEY, sha256 CHAR(64) NOT NULL, aplicado_en TIMESTAMP NOT NULL DEFAULT now())');
        foreach (glob($root . '/migrations/*.sql') as $migration) {
            $name = basename($migration);
            $checksum = hash_file('sha256', $migration);
            $existing = $db->execute('SELECT sha256 FROM sgpm.schema_migrations WHERE nombre=:nombre', array('nombre' => $name))->fetchColumn();
            if ($existing !== false) {
                if (!hash_equals($existing, $checksum)) { throw new RuntimeException('Migración aplicada modificada: ' . $name); }
                continue;
            }
            $transaction = new Sgpm\Infrastructure\Transaction($db);
            $transaction->run(array('usuario_id' => null, 'sistema' => true, 'ip' => '127.0.0.1',
                'request_id' => bin2hex(random_bytes(16))), function ($database) use ($migration, $name, $checksum) {
                Sgpm\Infrastructure\SqlScript::run($database, file_get_contents($migration));
                $database->execute('INSERT INTO sgpm.schema_migrations(nombre,sha256) VALUES(:nombre,:sha256)',
                    array('nombre' => $name, 'sha256' => $checksum));
            });
            echo $name . ": aplicada\n";
        }
    } finally { $db->execute('SELECT pg_advisory_unlock(534750, 1)'); }
} catch (Throwable $error) {
    fwrite(STDERR, "No se aplicaron todas las migraciones. Revisa configuración y registros locales.\n");
    exit(1);
}

