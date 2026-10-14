<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fixtures/ModuleTestContext.php';
return function ($assert) {
    $f=new SgpmModuleTestContext(); $service=new Sgpm\Modules\Proyectos\ProjectService($f->db);
    $p=$f->project(); $updated=$service->save($f->a,$p['id'],array('version'=>1,'nombre'=>'Auditoría Ñ'),$f->admin);
    $service->delete($p['id'],$updated['version'],$f->admin);
    $rows=$f->db->execute("SELECT * FROM sgpm.auditoria WHERE entidad='proyecto' AND entidad_id=:id ORDER BY id",array('id'=>$p['id']))->fetchAll();
    $assert(count($rows)===3 && $rows[0]['valores_anteriores']===null && $rows[2]['valores_nuevos']===null,'Snapshots INSERT/UPDATE/DELETE.');
    foreach ($rows as $row) {
        $assert((int)$row['usuario_id']===$f->admin['usuario_id'] && (int)$row['empresa_id_contexto']===$f->a && (int)$row['proyecto_id_contexto']===$p['id'],'Actor/ambito historicos.');
        $assert(filter_var($row['ip'],FILTER_VALIDATE_IP)!==false && preg_match('/^[a-f0-9]{32}$/D',$row['request_id'])===1,'IP/request_id.');
    }
    $global=(new Sgpm\Infrastructure\Transaction($f->db))->run($f->admin,function ($db) {
        $id=(int)$db->execute("INSERT INTO sgpm.empresa_vecina(nombre) VALUES('Identidad global') RETURNING id")->fetchColumn();
        $db->execute('DELETE FROM sgpm.empresa_vecina WHERE id=:id',array('id'=>$id)); return $id;
    });
    $assert($f->db->execute("SELECT empresa_id_contexto FROM sgpm.auditoria WHERE entidad='empresa_vecina' AND entidad_id=:id ORDER BY id LIMIT 1",array('id'=>$global))->fetchColumn()===null,'Identidad global no se atribuye a empresas.');
    $snapshots=$f->db->execute('SELECT valores_anteriores::text,valores_nuevos::text FROM sgpm.auditoria')->fetchAll();
    foreach ($snapshots as $row) {
        $text=json_encode($row); foreach (array('password_hash','token_hash','USERKEY','APIKEY','payload_cifrado',getenv('SGPM_FIXTURE_PASSWORD')) as $secret) { $assert(strpos($text,$secret)===false,'Snapshots sin secretos.'); }
    }
    $f->expect(409,function () use ($f) {
        $outbox=new Sgpm\Jobs\NotificationRepository($f->db,new Sgpm\Infrastructure\PayloadCipher($f->config));
        $id=$f->geo['usuario_id']; $user=(new Sgpm\Modules\Usuarios\UserRepository($f->db))->find($id);
        (new Sgpm\Modules\Usuarios\UserService($f->db,$outbox))->delete($id,$user['version'],$f->admin);
    });
};
