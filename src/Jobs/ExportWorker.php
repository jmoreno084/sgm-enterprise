<?php
declare(strict_types=1);
namespace Sgpm\Jobs;
use Sgpm\Modules\Reportes\ExportRepository;
class ExportWorker
{
    private $db;
    private $notifications;
    public function __construct(\Sgpm\Infrastructure\Database $db,\Sgpm\Modules\Reportes\ReportNotifications $notifications=null) { $this->db=$db; $this->notifications=$notifications; }
    public function once(int $onlyId=0): bool
    {
        $this->db->pdo()->beginTransaction();
        try {
            $sql="SELECT * FROM sgpm.exportacion_job WHERE estado='PENDIENTE' AND expira_en>timezone('UTC',now())";
            $params=array();
            if ($onlyId>0) { $sql.=' AND id=:id'; $params['id']=$onlyId; }
            $job=$this->db->execute($sql.' ORDER BY id FOR UPDATE SKIP LOCKED LIMIT 1',$params)->fetch();
            if (!$job) { $this->db->pdo()->commit(); return false; }
            $this->db->execute("UPDATE sgpm.exportacion_job SET estado='PROCESANDO',procesando_en=timezone('UTC',now()) WHERE id=:id",array('id'=>(int)$job['id'])); $this->db->pdo()->commit();
        } catch (\Throwable $error) { $this->db->pdo()->rollBack(); throw $error; }
        $temporary=null; $published=null;
        try {
            $repo=new ExportRepository($this->db); $context=array('usuario_id'=>(int)$job['usuario_id'],'ip'=>'127.0.0.1','request_id'=>bin2hex(random_bytes(16)));
            $repo->authorize($job,$context);
            $formats=array('PDF'=>array('Pdf','pdf'),'CSV'=>array('Csv','csv'),'XLSX'=>array('Xlsx','xlsx'),'GEOJSON'=>array('GeoJson','geojson'),'KML'=>array('Kml','kml'),'SHAPEFILE'=>array('Shapefile','zip'));
            $format=$formats[$job['formato']]; $name=bin2hex(random_bytes(32)).'.'.$format[1]; $published=ExportRepository::path($name); $temporary=$published.'.part';
            $job['empresa_nombre']=$this->db->execute('SELECT razon_social FROM sgpm.empresa WHERE id=:id',array('id'=>(int)$job['empresa_id']))->fetchColumn();
            $class='Sgpm\\Modules\\Reportes\\'.$format[0].'Exporter'; (new $class())->write($repo->records($job,$context),$temporary,$job);
            chmod($temporary,0600); $this->db->pdo()->beginTransaction();
            try {
                $current=$this->db->execute('SELECT * FROM sgpm.exportacion_job WHERE id=:id FOR UPDATE',array('id'=>(int)$job['id']))->fetch();
                $repo->authorize($current,$context);
                if ($current['estado']!=='PROCESANDO' || strtotime($current['expira_en'].' UTC')<=time()) { throw new \RuntimeException('Trabajo caducado.'); }
                if (!rename($temporary,$published)) { throw new \RuntimeException('Publicación incompleta.'); }
                $this->db->execute("UPDATE sgpm.exportacion_job SET estado='LISTO',archivo_relativo=:file,error_codigo=NULL WHERE id=:id",array('id'=>(int)$job['id'],'file'=>$name));
                if ($this->notifications!==null) { $this->notifications->ready($job); }
                $this->db->pdo()->commit(); $published=null;
            } catch (\Throwable $error) { $this->db->pdo()->rollBack(); throw $error; }
        } catch (\Throwable $error) {
            if ($published!==null && is_file($published)) { unlink($published); }
            $this->db->execute("UPDATE sgpm.exportacion_job SET estado='FALLIDO',archivo_relativo=NULL,error_codigo=:error WHERE id=:id AND estado='PROCESANDO'",array('id'=>(int)$job['id'],'error'=>$error instanceof \Sgpm\Http\HttpException?'ACCESO_REVOCADO':'GENERACION_FALLIDA'));
        } finally { if ($temporary!==null && is_file($temporary)) { unlink($temporary); } }
        return true;
    }
}
