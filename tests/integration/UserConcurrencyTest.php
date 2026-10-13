<?php
declare(strict_types=1);

return function ($assert) {
    $config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php');
    $config->assertTestDatabase(); $db=new Sgpm\Infrastructure\Database($config);
    $repository=new Sgpm\Modules\Usuarios\UserRepository($db);
    $admin=$repository->credentials('admin@example.invalid');
    $context=array('usuario_id'=>(int)$admin['id'],'ip'=>'127.0.0.5','request_id'=>bin2hex(random_bytes(16)));
    $users=new Sgpm\Modules\Usuarios\UserService($db,new Sgpm\Jobs\NotificationRepository($db,new Sgpm\Infrastructure\PayloadCipher($config)));
    $second=$users->create(array('correo'=>'race-admin@example.invalid','nombre'=>'Concurrente','numero_documento'=>'RACE','password'=>'clave segura concurrente','es_administrador'=>true),$context);
    $barrier=sys_get_temp_dir().'/sgpm-race-'.bin2hex(random_bytes(8));
    $processes=array();
    try {
        foreach (array(array((int)$admin['id'],(int)$admin['version']),array($second['id'],$second['version'])) as $account) {
            $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/fixtures/admin-race.php').' '.$account[0].' '.$account[1].' '.escapeshellarg($barrier);
            $process=proc_open($command,array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
            if (!is_resource($process)) { throw new RuntimeException('No se pudo preparar concurrencia.'); }
            fclose($pipes[0]); $processes[]=array($process,$pipes);
        }
        touch($barrier); $results=array();
        foreach ($processes as $item) {
            $results[]=trim(stream_get_contents($item[1][1]));
            $errors=stream_get_contents($item[1][2]); fclose($item[1][1]); fclose($item[1][2]);
            $exit=proc_close($item[0]); $assert($exit===0 && $errors==='','Procesos concurrentes sin errores.');
        }
        $processes=array(); sort($results);
        $assert($results===array('200','409'),'Una degradacion tiene exito y la otra se rechaza.');
        $assert((int)$db->execute("SELECT count(*) FROM sgpm.usuario WHERE estado='ACTIVO' AND es_administrador")->fetchColumn()===1,'Permanece un Administrador activo.');
    } finally {
        foreach ($processes as $item) { @proc_terminate($item[0]); @proc_close($item[0]); }
        if (is_file($barrier)) { unlink($barrier); }
        $survivor=$db->execute("SELECT id FROM sgpm.usuario WHERE estado='ACTIVO' AND es_administrador ORDER BY id LIMIT 1")->fetchColumn();
        $restoreContext=$context; $restoreContext['usuario_id']=(int)$survivor;
        $current=$repository->find((int)$admin['id']);
        if (!$current['es_administrador']) { $users->update($current['id'],array('version'=>$current['version'],'es_administrador'=>true),$restoreContext); }
        $currentSecond=$repository->find($second['id']);
        if ($currentSecond['es_administrador']) { $users->update($second['id'],array('version'=>$currentSecond['version'],'es_administrador'=>false),$context); }
    }
};
