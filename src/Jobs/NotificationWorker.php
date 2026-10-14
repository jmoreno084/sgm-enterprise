<?php
declare(strict_types=1);
namespace Sgpm\Jobs;

class NotificationWorker
{
    private $db;
    private $cipher;
    private $adapter;
    public function __construct(\Sgpm\Infrastructure\Database $db,\Sgpm\Infrastructure\PayloadCipher $cipher,\Sgpm\Infrastructure\CorporateEmailAdapter $adapter)
    { $this->db=$db; $this->cipher=$cipher; $this->adapter=$adapter; }
    public function once(): bool
    {
        $this->db->execute("UPDATE sgpm.notificaciones_outbox SET estado='INDETERMINADO',payload_cifrado='',procesado_en=timezone('UTC',now()),error_codigo='WORKER_PERDIDO' WHERE estado='PROCESANDO' AND disponible_en<timezone('UTC',now())-interval '5 minutes'");
        $this->db->pdo()->beginTransaction();
        try {
            $row=$this->db->execute("SELECT * FROM sgpm.notificaciones_outbox WHERE estado='PENDIENTE' AND disponible_en<=timezone('UTC',now()) ORDER BY id FOR UPDATE SKIP LOCKED LIMIT 1")->fetch();
            if (!$row) { $this->db->pdo()->commit(); return false; }
            $this->db->execute("UPDATE sgpm.notificaciones_outbox SET estado='PROCESANDO',disponible_en=timezone('UTC',now()) WHERE id=:id",array('id'=>(int)$row['id']));
            $this->db->pdo()->commit();
        } catch (\Throwable $error) { $this->db->pdo()->rollBack(); throw $error; }
        $result=array('estado'=>'FALLIDO','http_status'=>null,'error_codigo'=>'DESTINO_NO_AUTORIZADO');
        try {
            $user=$this->db->execute('SELECT id,correo,estado,es_administrador FROM sgpm.usuario WHERE id=:id',array('id'=>(int)$row['usuario_destino_id']))->fetch();
            $expired=$row['expira_en']!==null && strtotime($row['expira_en'].' UTC')<=time();
            $authorized=$user && $user['estado']==='ACTIVO' && !$expired;
            if ($authorized && $row['tipo']==='ALERTA_AUDITORIA') { $authorized=\Sgpm\Auth\Authorization::permits($user,'admin'); }
            if ($authorized && $row['empresa_id_contexto']!==null) {
                $member=$this->db->execute('SELECT rol,estado FROM sgpm.empresa_usuarios WHERE usuario_id=:usuario AND empresa_id=:empresa',array('usuario'=>(int)$user['id'],'empresa'=>(int)$row['empresa_id_contexto']))->fetch();
                $authorized=\Sgpm\Auth\Authorization::permits($user,'exportar',$member?:array());
            }
            if ($authorized) {
                $payload=$this->cipher->decrypt($row['payload_cifrado']);
                if ($row['tipo']==='RECUPERACION') {
                    $authorized=isset($payload['token']) && is_string($payload['token']) && (bool)$this->db->execute("SELECT id FROM sgpm.recuperacion_claves WHERE usuario_id=:usuario AND token_hash=:hash AND consumido_en IS NULL AND expira_en>timezone('UTC',now())",array('usuario'=>(int)$user['id'],'hash'=>hash('sha256',$payload['token'])))->fetch();
                }
                if ($row['tipo']==='REPORTE') {
                    $authorized=isset($payload['exportacion_id']) && is_int($payload['exportacion_id']);
                    if ($authorized) {
                        try { (new \Sgpm\Modules\Reportes\ExportRepository($this->db))->download($payload['exportacion_id'],array('usuario_id'=>(int)$user['id'])); }
                        catch (\Sgpm\Http\HttpException $error) { $authorized=false; }
                    }
                }
                if ($authorized) { $result=$this->adapter->send($user['correo'],$payload); }
            }
        } catch (\Throwable $error) { $result=array('estado'=>'INDETERMINADO','http_status'=>null,'error_codigo'=>'DESPACHO_AMBIGUO'); }
        $this->db->execute("UPDATE sgpm.notificaciones_outbox SET estado=:estado,http_status=:status,error_codigo=:error,payload_cifrado='',procesado_en=timezone('UTC',now()) WHERE id=:id AND estado='PROCESANDO'",array('estado'=>$result['estado'],'status'=>$result['http_status'],'error'=>$result['error_codigo'],'id'=>(int)$row['id']));
        return true;
    }
}
