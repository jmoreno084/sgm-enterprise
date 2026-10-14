<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $p=$f->project(); $other=$f->project();
    $neighbor=(new Sgpm\Infrastructure\Transaction($f->db))->run($f->admin,function ($db) use ($p) {
        $id=(int)$db->execute("INSERT INTO sgpm.empresa_vecina(nombre) VALUES('Vecina concurrente') RETURNING id")->fetchColumn();
        $db->execute("INSERT INTO sgpm.proyecto_empresas_vecinas(proyecto_id,empresa_vecina_id,color_hex,orden_registro) VALUES(:p,:n,'#8a2be2',1)",array('p'=>$p['id'],'n'=>$id)); return $id;
    });
    $code=$f->db->execute('SELECT codigo_expediente FROM public.geo_titulos_mineros ORDER BY codigo_expediente LIMIT 1')->fetchColumn();
    $barrier=sys_get_temp_dir().'/sgpm-project-race-'.bin2hex(random_bytes(8)); $processes=array();
    try {
        foreach (array('PROPIO','VECINO') as $affiliation) {
            $data=array('codigo_expediente'=>$code,'tipo_expediente'=>'TITULO','filiacion'=>$affiliation,'empresa_vecina_id'=>$affiliation==='PROPIO'?null:$neighbor);
            $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/fixtures/project-race.php');
            $process=proc_open($command,array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
            if (!is_resource($process)) { throw new RuntimeException('Proceso de carrera no disponible.'); }
            fwrite($pipes[0],json_encode(array('barrier'=>$barrier,'project'=>$p['id'],'data'=>$data,'context'=>$f->admin))); fclose($pipes[0]);
            $processes[]=array($process,$pipes);
        }
        touch($barrier); $results=array();
        foreach ($processes as $item) {
            $results[]=trim(stream_get_contents($item[1][1])); $errors=stream_get_contents($item[1][2]);
            fclose($item[1][1]); fclose($item[1][2]); $exit=proc_close($item[0]);
            $assert($exit===0 && $errors==='','Procesos RN12 sin fallos tecnicos.');
        }
        $processes=array(); sort($results); $assert($results===array('201','409'),'Propio/vecino concurrentes:201/409.');
        $assert((int)$f->db->execute('SELECT count(*) FROM sgpm.proyecto_expedientes WHERE proyecto_id=:id',array('id'=>$p['id']))->fetchColumn()===1,'Una sola fila RN12.');
    } finally {
        foreach ($processes as $item) { @proc_terminate($item[0]); @proc_close($item[0]); }
        if (is_file($barrier)) { unlink($barrier); }
    }
    $values=require dirname(__DIR__,2).'/config/config.php'; $values['db_user']=getenv('SGPM_MIGRATION_DB_USER'); $values['db_password']=getenv('SGPM_MIGRATION_DB_PASSWORD');
    $owner=new Sgpm\Infrastructure\Database(new Sgpm\Infrastructure\Configuration($values)); $owner->execute('SET ROLE sgpm_owner');
    $owner->execute("CREATE FUNCTION sgpm.fixture_project_audit_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Fallo fixture' USING ERRCODE='23514'; END; $$");
    try {
        $owner->execute('CREATE TRIGGER fixture_project_audit_failure BEFORE INSERT ON sgpm.auditoria FOR EACH ROW EXECUTE PROCEDURE sgpm.fixture_project_audit_failure()');
        try {
            (new Sgpm\Modules\Proyectos\ExpedienteService($f->db))->save($other['id'],0,array('codigo_expediente'=>$code,'tipo_expediente'=>'TITULO','filiacion'=>'PROPIO'),$f->admin);
            throw new RuntimeException('Fallo de auditoria no propagado.');
        } catch (PDOException $error) { $assert($error->getCode()==='23514','Error interno de auditoria se propaga para respuesta500.'); }
        $assert((int)$f->db->execute('SELECT count(*) FROM sgpm.proyecto_expedientes WHERE proyecto_id=:id',array('id'=>$other['id']))->fetchColumn()===0,'Fallo auditoria revierte asociacion.');
        $assert((new Sgpm\Modules\Proyectos\ProjectRepository($f->db))->find($other['id'])['version']===1,'Fallo auditoria no altera centroide/version.');
    } finally {
        $owner->execute('DROP TRIGGER fixture_project_audit_failure ON sgpm.auditoria');
        $owner->execute('DROP FUNCTION sgpm.fixture_project_audit_failure()');
    }
};
