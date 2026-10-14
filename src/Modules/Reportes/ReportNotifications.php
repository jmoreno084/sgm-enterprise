<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class ReportNotifications
{
    private $outbox;
    private $baseUrl;
    public function __construct(\Sgpm\Jobs\NotificationRepository $outbox,string $baseUrl) { $this->outbox=$outbox; $this->baseUrl=rtrim($baseUrl,'/'); }
    public function ready(array $job)
    {
        $this->outbox->enqueue('REPORTE',(int)$job['usuario_id'],array('asunto'=>'SGPM: reporte disponible','mensaje'=>'Tu reporte '.$job['formato'].' está disponible hasta '.$job['expira_en'].' UTC. Inicia sesión para descargarlo: '.$this->baseUrl.'/api/v1/exportaciones/'.$job['id'].'/archivo','exportacion_id'=>(int)$job['id']),
            'reporte:'.$job['id'].':'.$job['usuario_id'],$job['expira_en'],(int)$job['empresa_id']);
    }
}
