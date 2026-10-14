<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/bootstrap.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
$config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php'); $config->assertTestDatabase();
$server=json_decode(file_get_contents('/tmp/sgpm-acceptance.json'),true); $dir=dirname($server['ca']);
if (!preg_match('~^/tmp/sgpm-tls-[a-f0-9]{16}$~D',$dir)) { throw new RuntimeException('Servidor fixture requerido.'); }
$path=$dir.'/apache.conf'; $old=file_get_contents($path);
preg_match_all('/^\s*Header always set .*$/m',file_get_contents(dirname(__DIR__,2).'/ops/apache/sgpm.conf'),$headers);
$new=preg_replace('/^\s*Header always set .*\n/m','',$old);
$new=preg_replace('/(SSLCertificateKeyFile[^\n]+\n)/','$1'.implode("\n",$headers[0])."\n",$new);
if (strpos($new,'AddOutputFilterByType DEFLATE')===false) { $new=str_replace(' php_flag display_errors Off'," <IfModule mod_deflate.c>\n AddOutputFilterByType DEFLATE application/json application/geo+json text/html text/css application/javascript text/javascript\n </IfModule>\n php_flag display_errors Off",$new); }
file_put_contents($path,$new);
exec('/usr/sbin/apache2 -t -f '.escapeshellarg($path).' 2>&1',$output,$code);
if ($code!==0) { file_put_contents($path,$old); throw new RuntimeException('Configuración fixture inválida.'); }
exec('/usr/sbin/apache2 -f '.escapeshellarg($path).' -k graceful 2>&1',$output,$code);
if ($code!==0) { throw new RuntimeException('No se pudo recargar fixture.'); }
echo "Apache desechable recargado; certificado y sesiones conservados.\n";
