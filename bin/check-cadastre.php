<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    $config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__).'/config/config.php');
    $schema=new Sgpm\Modules\Catastro\CadastreSchema(new Sgpm\Infrastructure\Database($config));
    foreach (array('TITULO','SOLICITUD') as $type) { $schema->columns($type); echo $type.": 30 columnas/tipos/NULL/unicidad/GiST conformes.\n"; }
} catch (Throwable $error) { fwrite(STDERR,"Catastro incompatible o no disponible; no se modificaron las fuentes.\n"); exit(1); }
