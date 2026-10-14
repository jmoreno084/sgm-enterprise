<?php
declare(strict_types=1);
namespace Sgpm\Jobs;
class ReportScheduler
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function once(\DateTimeImmutable $now=null): int
    {
        $now=$now?:new \DateTimeImmutable('now',new \DateTimeZone('UTC')); $local=$now->setTimezone(new \DateTimeZone('America/Bogota'));
        $context=array('usuario_id'=>null,'sistema'=>true,'ip'=>'127.0.0.1','request_id'=>bin2hex(random_bytes(16)));
        return (new \Sgpm\Infrastructure\Transaction($this->db))->run($context,function () use ($local) {
            $this->db->execute('SELECT pg_advisory_xact_lock(534750,4)'); $count=0;
            $schedules=$this->db->execute("SELECT * FROM sgpm.programacion_reportes WHERE estado='ACTIVO' AND hora_local<=:hour ORDER BY id FOR UPDATE",array('hour'=>$local->format('H:i:s')))->fetchAll();
            foreach ($schedules as $schedule) {
                $filters=json_decode($schedule['filtros'],true);
                $users=$this->db->execute('SELECT usuario_id FROM sgpm.programacion_destinatarios WHERE programacion_id=:id ORDER BY usuario_id',array('id'=>(int)$schedule['id']))->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($users as $user) {
                    $access=new \Sgpm\Auth\ResourceAccess($this->db); $actor=array('usuario_id'=>(int)$user);
                    try { $access->company((int)$schedule['empresa_id'],'exportar',$actor); if (isset($filters['proyecto_id'])) { $p=$access->project($filters['proyecto_id'],'exportar',$actor); if ((int)$p['empresa_id']!==(int)$schedule['empresa_id']) { continue; } } }
                    catch (\Sgpm\Http\HttpException $error) { continue; }
                    $count+=$this->db->execute("INSERT INTO sgpm.exportacion_job(usuario_id,empresa_id,proyecto_id,formato,filtros,programacion_id,fecha_programada) VALUES(:user,:company,:project,:format,:filters,:schedule,:day) ON CONFLICT(programacion_id,fecha_programada,usuario_id) DO NOTHING",array('user'=>(int)$user,'company'=>(int)$schedule['empresa_id'],'project'=>isset($filters['proyecto_id'])?$filters['proyecto_id']:null,'format'=>$schedule['formato'],'filters'=>$schedule['filtros'],'schedule'=>(int)$schedule['id'],'day'=>$local->format('Y-m-d')))->rowCount();
                }
            } return $count;
        });
    }
}
