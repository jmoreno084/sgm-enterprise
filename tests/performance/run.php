<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/src/bootstrap.php';
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
// Run on a separate client, not in the target PHP/DB service containers.
$options=getopt('',array('users:','warmup:','duration:','sessions:','ca:','url:','resolve:','output:','round:','diagnostic','network-profile:'));
$users=isset($options['users'])?(int)$options['users']:50; $warmup=isset($options['warmup'])?(int)$options['warmup']:120; $duration=isset($options['duration'])?(int)$options['duration']:600;
if ($users!==50 || $warmup<0 || $duration<1 || (!isset($options['diagnostic']) && ($warmup!==120 || $duration!==600))) { fwrite(STDERR,"Protocolo:50 usuarios/120s/600s; intervalos menores requieren --diagnostic.\n"); exit(2); }
$root=dirname(__DIR__,2); $sessionFile=isset($options['sessions'])?$options['sessions']:$root.'/storage/acceptance/performance-sessions.json';
$meta=json_decode(file_get_contents($sessionFile),true);
if (!is_array($meta) || count($meta['users'])!==50 || count(array_unique(array_column($meta['users'],'cookie')))!==50) { throw new RuntimeException('Se requieren50 sesiones fixture distintas.'); }
$base=isset($options['url'])?rtrim($options['url'],'/'):'https://localhost:18443'; $url=parse_url($base);
if (!$url || $url['scheme']!=='https' || isset($url['user']) || isset($url['pass']) || isset($url['query'])) { throw new RuntimeException('URL HTTPS requerida.'); }
$ca=isset($options['ca'])?$options['ca']:$root.'/storage/acceptance/server.crt'; if (!is_file($ca)) { throw new RuntimeException('CA explícita requerida.'); }
$output=isset($options['output'])?$options['output']:$root.'/ops/evidence/performance-run.json';
$multi=curl_multi_init(); $active=array(); $states=array(); $samples=array(); $failures=array(); $counts=array();
$start=microtime(true); $measureStart=$start+$warmup; $finish=$measureStart+$duration; $lastProgress=$start; $previousLoop=$start; $interruption=null;
foreach ($meta['users'] as $index=>$user) {
    $weights=$user['role']==='GEOLOGO'?array('search'=>40,'map'=>12,'identify'=>8,'edit'=>20):array('search'=>50,'map'=>17,'identify'=>8,'export'=>5);
    $plan=array(); foreach ($weights as $kind=>$number) { for ($i=0;$i<$number;++$i) { $plan[]=$kind; } }
    mt_srand(534750+$index); shuffle($plan);
    $states[$index]=array('user'=>$user,'plan'=>$plan,'index'=>0,'due'=>$start,'operation'=>null,'version'=>null,'page'=>1);
}
$enqueue=function (int $index,string $method,string $path,array $body=null) use (&$states,&$active,$multi,$base,$ca,$options,$url) {
    $user=$states[$index]['user']; $handle=curl_init($base.$path);
    $headers=array('Accept: application/json','Cookie: '.$user['cookie'],'X-CSRF-Token: '.$user['csrf']);
    $settings=array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_CAINFO=>$ca,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_TIMEOUT=>60,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_ENCODING=>'');
    if ($body!==null) { $headers[]='Content-Type: application/json'; $settings[CURLOPT_POSTFIELDS]=json_encode($body); }
    if (isset($options['resolve'])) { $settings[CURLOPT_RESOLVE]=array($url['host'].':'.(isset($url['port'])?$url['port']:443).':'.$options['resolve']); }
    $settings[CURLOPT_HTTPHEADER]=$headers; curl_setopt_array($handle,$settings); curl_multi_add_handle($multi,$handle);
    $active[(int)$handle]=array('handle'=>$handle,'index'=>$index,'path'=>$path,'started'=>microtime(true));
};
$begin=function (int $index) use (&$states,$meta,$enqueue) {
    $state=&$states[$index]; $kind=$state['plan'][$state['index']++%80]; $state['operation']=$kind; $state['started']=microtime(true); $state['page']=1;
    if ($kind==='search') { $enqueue($index,'GET','/api/v1/catastro?mineral=Oro&municipio=Municipio%20'.($state['index']%100)); }
    elseif ($kind==='map') { $enqueue($index,'GET','/api/v1/proyectos/'.$meta['projects']['500'].'/mapa?page_size=100&page=1'); }
    elseif ($kind==='identify') { $enqueue($index,'GET','/api/v1/proyectos/'.$meta['projects']['500'].'/identify/'.$meta['identify']); }
    elseif ($kind==='edit') { $state['version']=null; $enqueue($index,'GET','/api/v1/proyectos/'.$state['user']['edit_project']); }
    else { $enqueue($index,'POST','/api/v1/empresas/'.$meta['company'].'/exportaciones',array('formato'=>'CSV','proyecto_id'=>$meta['projects']['500'])); }
};
try {
    do {
        $now=microtime(true);
        $gap=$now-$previousLoop; $previousLoop=$now;
        if ($gap>10 || $gap < -1) {
            $interruption=$gap;
            foreach ($active as $pending) { $failures[]=array('kind'=>$states[$pending['index']]['operation'],'http'=>null,'curl_code'=>null,'error'=>'CLOCK_OR_CLIENT_INTERRUPTION','seconds'=>$now-$pending['started']); }
            break;
        }
        foreach ($states as $index=>$candidate) { if ($now<$finish && $candidate['operation']===null && $candidate['due']<=$now) { $begin($index); } }
        do { $status=curl_multi_exec($multi,$running); } while ($status===CURLM_CALL_MULTI_PERFORM);
        if ($status!==CURLM_OK) { throw new RuntimeException('Cliente multi no disponible.'); }
        while ($done=curl_multi_info_read($multi)) {
            $handle=$done['handle']; $request=$active[(int)$handle]; $index=$request['index']; $state=&$states[$index];
            $raw=curl_multi_getcontent($handle); $http=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE); $elapsed=microtime(true)-$request['started'];
            $json=json_decode($raw,true); $valid=$done['result']===CURLE_OK && in_array($http,array(200,202),true) && isset($json['data']);
            $measured=$state['started']>=$measureStart && $state['started']<$finish;
            curl_multi_remove_handle($multi,$handle); curl_close($handle); unset($active[(int)$handle]);
            if ($measured) {
                $samples[]=array('kind'=>$state['operation'],'phase'=>$state['operation']==='edit'&&$state['version']===null?'read':'request','seconds'=>$elapsed,'http'=>$http,'valid'=>$valid);
                if (!$valid) { $failures[]=array('kind'=>$state['operation'],'http'=>$http,'curl_code'=>$done['result'],'request_id'=>isset($json['meta']['request_id'])?$json['meta']['request_id']:null); }
            }
            if ($valid && $state['operation']==='map' && $json['data']['meta']['next_page']!==null) {
                $enqueue($index,'GET','/api/v1/proyectos/'.$meta['projects']['500'].'/mapa?page_size=100&page='.$json['data']['meta']['next_page']); continue;
            }
            if ($valid && $state['operation']==='edit' && $state['version']===null) {
                $state['version']=(int)$json['data']['version']; $enqueue($index,'PUT','/api/v1/proyectos/'.$state['user']['edit_project'],array('version'=>$state['version'],'observaciones'=>'Carga reproducible '.$state['index'])); continue;
            }
            if ($measured) { $kind=$state['operation']; if (!isset($counts[$kind])) { $counts[$kind]=0; } ++$counts[$kind]; }
            $state['operation']=null; $state['due']=microtime(true)+mt_rand(1000,3000)/1000.0;
        }
        $now=microtime(true);
        if ($now-$lastProgress>=30) { echo 'Carga: '.(int)($now-$start).'s; '.count($samples).' muestras medidas; '.count($failures)." fallos.\n"; $lastProgress=$now; }
        if ($running) { if (curl_multi_select($multi,0.05)===-1) { usleep(10000); } } else { usleep(10000); }
    } while (microtime(true)<$finish || count($active)>0);
} finally { foreach ($active as $request) { curl_multi_remove_handle($multi,$request['handle']); curl_close($request['handle']); } curl_multi_close($multi); }
$summary=array(); foreach ($samples as $sample) {
    $key=$sample['kind']; if (!isset($summary[$key])) { $summary[$key]=array('valid'=>0,'failed'=>0,'times'=>array()); }
    if ($sample['valid']) { ++$summary[$key]['valid']; $summary[$key]['times'][]=$sample['seconds']; } else { ++$summary[$key]['failed']; }
}
$standardPass=true;
foreach ($summary as $kind=>&$item) {
    sort($item['times'],SORT_NUMERIC); $n=count($item['times']); $item['maximum_seconds']=$n?max($item['times']):null; $item['p95_seconds']=$n?$item['times'][max(0,(int)ceil($n*0.95)-1)]:null; unset($item['times']);
    if (in_array($kind,array('search','identify'),true) && ($item['maximum_seconds']===null || $item['maximum_seconds']>=3 || $item['failed']>0)) { $standardPass=false; }
} unset($item);
$report=array('php'=>PHP_VERSION,'users'=>50,'warmup_seconds'=>$warmup,'measurement_seconds'=>$duration,'actual_elapsed_seconds'=>microtime(true)-$start,'protocol_uninterrupted'=>$interruption===null,'interruption_seconds'=>$interruption,'round'=>isset($options['round'])?$options['round']:'unspecified','diagnostic'=>isset($options['diagnostic']),'network_profile'=>isset($options['network-profile'])?$options['network-profile']:'not_verified','operations'=>$counts,'summary'=>$summary,'failures'=>$failures,'samples'=>$samples,'all_standard_samples_under_3s'=>$standardPass,'browser_rendering_measured'=>false,'generation_measured'=>false,'tls_verified'=>true);
file_put_contents($output,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo count($samples).' muestras; '.count($failures)." fallos; detalle saneado guardado.\n";
exit(!$failures && $standardPass && $interruption===null?0:1);
