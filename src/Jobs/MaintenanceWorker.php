<?php
declare(strict_types=1);
namespace Sgpm\Jobs;
class MaintenanceWorker
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function once(): int
    {
        $count=0;
        $this->db->execute("UPDATE sgpm.exportacion_job SET estado='FALLIDO',error_codigo='WORKER_PERDIDO' WHERE estado='PROCESANDO' AND procesando_en<timezone('UTC',now())-interval '2 hours'");
        $rows=$this->db->execute("UPDATE sgpm.exportacion_job SET estado='EXPIRADO' WHERE expira_en<=timezone('UTC',now()) AND (estado<>'EXPIRADO' OR archivo_relativo IS NOT NULL) RETURNING id,archivo_relativo")->fetchAll();
        foreach ($rows as $row) {
            if ($row['archivo_relativo']!==null) { $path=\Sgpm\Modules\Reportes\ExportRepository::path($row['archivo_relativo']); if (is_file($path) && !unlink($path)) { continue; } }
            $this->db->execute('UPDATE sgpm.exportacion_job SET archivo_relativo=NULL WHERE id=:id',array('id'=>(int)$row['id'])); ++$count;
        }
        $this->db->execute("UPDATE sgpm.notificaciones_outbox SET estado='FALLIDO',payload_cifrado='',procesado_en=timezone('UTC',now()),error_codigo='EXPIRADO' WHERE estado='PENDIENTE' AND expira_en<=timezone('UTC',now())");
        $this->db->execute("UPDATE sgpm.notificaciones_outbox SET estado='INDETERMINADO',payload_cifrado='',procesado_en=timezone('UTC',now()),error_codigo='WORKER_PERDIDO' WHERE estado='PROCESANDO' AND disponible_en<timezone('UTC',now())-interval '5 minutes'");
        $this->db->execute("UPDATE sgpm.notificaciones_outbox SET payload_cifrado='' WHERE estado IN ('ACEPTADO','FALLIDO','INDETERMINADO') AND payload_cifrado<>''");
        $this->db->execute("DELETE FROM sgpm.notificaciones_outbox WHERE estado IN ('ACEPTADO','FALLIDO','INDETERMINADO') AND creado_en<timezone('UTC',now())-interval '30 days'");
        foreach (glob(\Sgpm\Modules\Reportes\ExportRepository::directory().'/*.part') as $path) { if (preg_match('/^[a-f0-9]{64}\.(pdf|xlsx|csv|geojson|kml|zip)\.part$/D',basename($path)) && filemtime($path)<time()-7200) { unlink($path); } }
        return $count;
    }
}
