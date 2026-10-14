<?php
declare(strict_types=1);
namespace Sgpm\Jobs;

class AuditAlertProducer
{
    private $db;
    private $outbox;
    public function __construct(\Sgpm\Infrastructure\Database $db,NotificationRepository $outbox) { $this->db=$db; $this->outbox=$outbox; }
    public function once(\DateTimeImmutable $now=null): int
    {
        $context=array('usuario_id'=>null,'sistema'=>true,'ip'=>'127.0.0.1','request_id'=>bin2hex(random_bytes(16)));
        return (new \Sgpm\Infrastructure\Transaction($this->db))->run($context,function () use ($now) { return $this->produce($now); });
    }
    private function produce(\DateTimeImmutable $now=null): int
    {
        $this->db->execute('SELECT pg_advisory_xact_lock(534750,3)');
        $now=$now?:new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
        $limit=$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:00');
        // Closed minute groups avoid racing updates within the same minute.
        // Match the 30-day outbox retention so purged keys cannot replay old events.
        $groups=$this->db->execute("SELECT date_trunc('minute',fecha)::text AS minuto,count(*) AS cantidad FROM sgpm.auditoria
            WHERE fecha<CAST(:limit AS timestamp) AND fecha>=CAST(:since AS timestamp)-interval '30 days'
            AND (accion='DELETE' OR (accion='UPDATE' AND (
                (valores_nuevos->>'estado'='INACTIVO' AND valores_anteriores->>'estado' IS DISTINCT FROM valores_nuevos->>'estado')
                OR valores_anteriores->>'rol' IS DISTINCT FROM valores_nuevos->>'rol'
                OR valores_anteriores->>'es_administrador' IS DISTINCT FROM valores_nuevos->>'es_administrador')))
            GROUP BY date_trunc('minute',fecha) ORDER BY date_trunc('minute',fecha)",array('limit'=>$limit,'since'=>$limit))->fetchAll();
        $admins=$this->db->execute("SELECT id FROM sgpm.usuario WHERE estado='ACTIVO' AND es_administrador ORDER BY id")->fetchAll(); $count=0;
        foreach ($groups as $group) {
            foreach ($admins as $admin) {
                $key='audit:'.$group['minuto'].':'.$admin['id'];
                if (!$this->db->execute('SELECT id FROM sgpm.notificaciones_outbox WHERE deduplicacion=:key',array('key'=>$key))->fetch()) {
                    if ($this->outbox->enqueue('ALERTA_AUDITORIA',(int)$admin['id'],\Sgpm\Modules\Auditoria\AuditAlerts::payload($group['minuto'],(int)$group['cantidad']),$key,gmdate('Y-m-d H:i:s',time()+86400))) { ++$count; }
                }
            }
        }
        return $count;
    }
}
