<?php
declare(strict_types=1);
namespace Sgpm\Modules\Auditoria;

class AuditAlerts
{
    public static function payload(string $minute,int $count): array
    {
        return array('asunto'=>'SGPM: resumen de cambios administrativos',
            'mensaje'=>'<p>Cambios de roles, desactivaciones o borrados registrados en '.htmlspecialchars($minute,ENT_QUOTES,'UTF-8').' UTC: '.(int)$count.'. Consulta la auditoría autenticada para revisar el detalle.</p>');
    }
}
