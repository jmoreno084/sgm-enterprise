<?php
declare(strict_types=1);
return function (Sgpm\Infrastructure\Database $db,string $password) {
    if (strlen($password)<12 || strlen($password)>72) { throw new RuntimeException('Clave fixture requerida.'); }
    $count=(int)$db->execute('SELECT (SELECT count(*) FROM public.geo_titulos_mineros)+(SELECT count(*) FROM public.geo_solicitudes_mineras)')->fetchColumn();
    if ($count!==100000) { throw new RuntimeException('Se requieren100000 fuentes fixture.'); }
    $context=array('usuario_id'=>null,'sistema'=>true,'ip'=>'127.0.0.1','request_id'=>bin2hex(random_bytes(16)));
    return (new Sgpm\Infrastructure\Transaction($db))->run($context,function ($db) use ($password) {
        if ($db->execute("SELECT id FROM sgpm.empresa WHERE nit='SGPM-PERF'")->fetch()) { throw new RuntimeException('Fixture de carga ya existe; no se reemplazan datos.'); }
        $company=(int)$db->execute("INSERT INTO sgpm.empresa(nit,razon_social,correo,ciudad,departamento) VALUES('SGPM-PERF','Carga reproducible Ñ','performance@example.invalid','Bogotá','Cundinamarca') RETURNING id")->fetchColumn();
        $users=array();
        for ($i=0;$i<50;++$i) {
            $role=$i<10?'GEOLOGO':($i%2?'GERENTE':'INVERSIONISTA'); $email='performance'.str_pad((string)$i,2,'0',STR_PAD_LEFT).'@example.invalid';
            $id=(int)$db->execute('INSERT INTO sgpm.usuario(correo,password_hash,numero_documento,nombre) VALUES(:email,:hash,:document,:name) RETURNING id',array('email'=>$email,'hash'=>password_hash($password,PASSWORD_BCRYPT,array('cost'=>10)),'document'=>'PERF-'.$i,'name'=>'Carga Ñ '.$i))->fetchColumn();
            $db->execute('INSERT INTO sgpm.empresa_usuarios(empresa_id,usuario_id,rol) VALUES(:company,:user,:role)',array('company'=>$company,'user'=>$id,'role'=>$role));
            $edit=(int)$db->execute("INSERT INTO sgpm.proyecto(empresa_id,nombre,mineral_principal) VALUES(:company,:name,'Oro') RETURNING id",array('company'=>$company,'name'=>'Edición carga '.$i))->fetchColumn();
            $users[]=array('id'=>$id,'email'=>$email,'role'=>$role,'edit_project'=>$edit);
        }
        $projects=array();
        foreach (array(500,5000) as $size) {
            $id=(int)$db->execute("INSERT INTO sgpm.proyecto(empresa_id,nombre,mineral_principal) VALUES(:company,:name,'Oro') RETURNING id",array('company'=>$company,'name'=>'Carga '.$size.' polígonos'))->fetchColumn();
            $db->execute("INSERT INTO sgpm.proyecto_expedientes(proyecto_id,codigo_expediente,tipo_expediente,filiacion) SELECT :project,'PERF-T-'||lpad(i::text,5,'0'),'TITULO','PROPIO' FROM generate_series(1,:size) i",array('project'=>$id,'size'=>$size));
            (new Sgpm\Modules\Proyectos\CentroidService($db))->refresh($id,$users[0]['id']);
            $projects[(string)$size]=$id;
        }
        $identify=(int)$db->execute('SELECT id FROM sgpm.proyecto_expedientes WHERE proyecto_id=:project ORDER BY id LIMIT 1',array('project'=>$projects['500']))->fetchColumn();
        return array('company'=>$company,'projects'=>$projects,'identify'=>$identify,'users'=>$users,'rows'=>100000,'vertices'=>array('500'=>50000,'5000'=>500000));
    });
};
