<?php
declare(strict_types=1);

// Exact fixture responses only; this endpoint is exposed exclusively by LocalApache.
if (getenv('SGPM_ENV')!=='test') { http_response_code(404); exit; }
header('Content-Type: text/plain; charset=utf-8');
$keys=array_keys($_POST); sort($keys);
$expected=array('APIKEY','USERKEY','asunto','email','mensaje'); sort($expected);
$valid=$_SERVER['REQUEST_METHOD']==='POST' && $keys===$expected
    && strpos(strtolower($_SERVER['CONTENT_TYPE']),'application/x-www-form-urlencoded')===0
    && $_POST['USERKEY']==='fixture-userkey' && $_POST['APIKEY']==='fixture-apikey'
    && $_POST['email']==='fixture@example.invalid' && $_POST['asunto']==='Prueba correo áé'
    && $_POST['mensaje']==='Mensaje con & y <etiqueta>';
if (!$valid) { http_response_code(400); echo 'fixture-invalid-request'; exit; }
$mode=isset($_SERVER['PATH_INFO'])?trim($_SERVER['PATH_INFO'],'/'):'accepted';
if ($mode==='timeout') { usleep(1500000); echo 'fixture-late'; exit; }
if ($mode==='partial') {
    header('Content-Length: 100'); header('Connection: close');
    echo 'part';
    while (ob_get_level()>0) { ob_end_flush(); }
    flush();
    exit;
}
if ($mode==='failure') { http_response_code(500); echo 'fixture-failure'; exit; }
if ($mode==='negative') { echo 'fixture-rejected'; exit; }
if ($mode==='unknown') { echo 'fixture-unknown'; exit; }
if ($mode==='empty') { exit; }
echo 'fixture-accepted';
