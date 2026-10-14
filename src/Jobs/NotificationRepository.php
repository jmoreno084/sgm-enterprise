<?php
declare(strict_types=1);
namespace Sgpm\Jobs;

class NotificationRepository
{
    private $db;
    private $cipher;
    public function __construct(\Sgpm\Infrastructure\Database $db, \Sgpm\Infrastructure\PayloadCipher $cipher)
    { $this->db=$db; $this->cipher=$cipher; }
    public function enqueue(string $type,int $userId,array $payload,string $dedup,string $expires,int $companyId=0)
    {
        if (!$this->db->pdo()->inTransaction()) { throw new \LogicException('Outbox requiere transaccion.'); }
        return $this->db->execute('INSERT INTO sgpm.notificaciones_outbox(tipo,usuario_destino_id,empresa_id_contexto,payload_cifrado,deduplicacion,expira_en) VALUES(:tipo,:usuario,:empresa,:payload,:dedup,:expira) ON CONFLICT (deduplicacion) DO NOTHING',
            array('tipo'=>$type,'usuario'=>$userId,'empresa'=>$companyId===0?null:$companyId,'payload'=>$this->cipher->encrypt($payload),'dedup'=>$dedup,'expira'=>$expires))->rowCount()===1;
    }
}
