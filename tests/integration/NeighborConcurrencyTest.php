<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $p=$f->project(); $service=new Sgpm\Modules\Vecinas\ProjectNeighborService($f->db);
    $barrier=sys_get_temp_dir().'/sgpm-neighbor-race-'.bin2hex(random_bytes(8)); $processes=array();
    try {
        foreach (array('Concurrente A','Concurrente B') as $name) {
            $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/fixtures/neighbor-race.php');
            $process=proc_open($command,array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
            if (!is_resource($process)) { throw new RuntimeException('No se pudo iniciar la carrera vecina.'); }
            fwrite($pipes[0],json_encode(array('project'=>$p['id'],'name'=>$name,'barrier'=>$barrier,'context'=>$f->admin))); fclose($pipes[0]); $processes[]=array($process,$pipes);
        }
        touch($barrier); $results=array();
        foreach ($processes as $item) {
            $results[]=json_decode(stream_get_contents($item[1][1]),true); $errors=stream_get_contents($item[1][2]); fclose($item[1][1]); fclose($item[1][2]);
            $assert(proc_close($item[0])===0 && $errors==='','Carrera vecina sin fallos tecnicos.');
        }
        $processes=array();
        $assert(count(array_unique(array_column($results,'orden_registro')))===2 && count(array_unique(array_column($results,'color_hex')))===2,'Ordinal/color distintos bajo concurrencia.');
    } finally { foreach ($processes as $item) { @proc_terminate($item[0]); @proc_close($item[0]); } if (is_file($barrier)) { unlink($barrier); } }
    $first=$results[0]; $service->delete($p['id'],$first['empresa_vecina_id'],1,$f->admin);
    $next=$service->save($p['id'],0,array('nueva_identidad'=>array('nombre'=>'Posterior')),$f->admin);
    $assert($next['orden_registro']===3 && $next['color_hex']!==$first['color_hex'],'No recicla ordinal/color al eliminar.');
    $f->expect(409,function () use ($service,$p,$next,$f) { $service->save($p['id'],$next['empresa_vecina_id'],array('version'=>99,'observaciones'=>'Obsoleto'),$f->admin); });
    $orphan='NO-ORPHAN-'.bin2hex(random_bytes(5));
    (new Sgpm\Infrastructure\Transaction($f->db))->run($f->admin,function ($db) use ($p) { $db->execute('UPDATE sgpm.proyecto SET vecina_secuencia=2147483000 WHERE id=:id',array('id'=>$p['id'])); });
    try {
        $f->expect(409,function () use ($service,$p,$orphan,$f) { $service->save($p['id'],0,array('nueva_identidad'=>array('nombre'=>$orphan)),$f->admin); });
        $assert((int)$f->db->execute('SELECT count(*) FROM sgpm.empresa_vecina WHERE nombre=:name',array('name'=>$orphan))->fetchColumn()===0,'Fallo de estilo revierte identidad y vinculo.');
    } finally { (new Sgpm\Infrastructure\Transaction($f->db))->run($f->admin,function ($db) use ($p) { $db->execute('UPDATE sgpm.proyecto SET vecina_secuencia=3 WHERE id=:id',array('id'=>$p['id'])); }); }
};
