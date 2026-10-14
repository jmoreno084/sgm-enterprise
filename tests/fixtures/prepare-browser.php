<?php
declare(strict_types=1);
// Only disposable acceptance state; never call against an existing deployment.
require dirname(__DIR__,2).'/src/bootstrap.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
$config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php');
$config->assertTestDatabase(); $db=new Sgpm\Infrastructure\Database($config);
if ((int)$db->execute("SELECT count(*) FROM sgpm.empresa WHERE nit IN ('SGPM-TEST-A','SGPM-TEST-B')")->fetchColumn()!==2) { throw new RuntimeException('Fixtures A/B requeridas.'); }
$db->execute('DELETE FROM sgpm.limites_acceso');
echo "Contadores de las pruebas previas reiniciados para revisión desechable.\n";
