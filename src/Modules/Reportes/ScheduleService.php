<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
use Sgpm\Auth\ResourceAccess;
use Sgpm\Infrastructure\Fields;
use Sgpm\Infrastructure\Mutation;
class ScheduleService
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function listing(int $company,array $context): array
    {
        $access=new ResourceAccess($this->db); $access->admin($context); $access->company($company,'consultar',$context);
        $rows=$this->db->execute('SELECT * FROM sgpm.programacion_reportes WHERE empresa_id=:company ORDER BY id',array('company'=>$company))->fetchAll();
        foreach ($rows as &$row) { $row=$this->dto($row); } unset($row); return $rows;
    }
    private function dto(array $row): array
    {
        $row=Fields::row($row); $row['filtros']=json_decode($row['filtros'],true);
        $row['destinatarios']=array_map('intval',$this->db->execute('SELECT usuario_id FROM sgpm.programacion_destinatarios WHERE programacion_id=:id ORDER BY usuario_id',array('id'=>$row['id']))->fetchAll(\PDO::FETCH_COLUMN)); return $row;
    }
    public function save(int $company,int $id,array $data,array $context): array
    {
        (new ResourceAccess($this->db))->admin($context);
        return Mutation::run($this->db,$context,function () use ($company,$id,$data,$context) {
            $access=new ResourceAccess($this->db); $access->admin($context); $old=array();
            if ($id) { $old=$this->db->execute('SELECT * FROM sgpm.programacion_reportes WHERE id=:id FOR UPDATE',array('id'=>$id))->fetch(); if (!$old) { ResourceAccess::missing(); } $company=(int)$old['empresa_id']; Mutation::version($old,$data); $old=$this->dto($old); }
            $access->company($company,'consultar',$context);
            Fields::only($data,array('formato','filtros','hora_local','destinatarios','estado','version'));
            $merged=array_merge(array('hora_local'=>'09:00','estado'=>'INACTIVO','filtros'=>array(),'destinatarios'=>array()),$old,$data);
            $format=ExportRepository::format(Fields::text($merged,'formato',20));
            if (!is_array($merged['filtros']) || !is_array($merged['destinatarios']) || count($merged['destinatarios'])>1000) { self::invalid(); }
            $filters=ExportRepository::filters($merged['filtros']); $state=Fields::state($merged);
            $hour=Fields::text($merged,'hora_local',8); if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?$/D',$hour)) { self::invalid(); }
            if (isset($filters['proyecto_id'])) { $p=$access->project($filters['proyecto_id'],'exportar',$context); if ((int)$p['empresa_id']!==$company) { ResourceAccess::missing(); } }
            $recipients=array();
            foreach ($merged['destinatarios'] as $recipient) {
                if (!is_int($recipient) || $recipient<1) { self::invalid(); }
                try { $access->company($company,'exportar',array('usuario_id'=>$recipient)); }
                catch (\Sgpm\Http\HttpException $error) { throw new \Sgpm\Http\HttpException(400,'DESTINATARIO_NO_AUTORIZADO','Cada destinatario debe estar activo y poder exportar en esta empresa.'); }
                $recipients[$recipient]=$recipient;
            }
            if ($state==='ACTIVO' && !$recipients) { self::invalid(); }
            $params=array('format'=>$format,'filters'=>json_encode((object)$filters),'hour'=>$hour,'state'=>$state);
            if ($id) { $params['id']=$id; $row=$this->db->execute('UPDATE sgpm.programacion_reportes SET formato=:format,filtros=:filters,hora_local=:hour,estado=:state,version=version+1 WHERE id=:id RETURNING *',$params)->fetch(); $this->db->execute('DELETE FROM sgpm.programacion_destinatarios WHERE programacion_id=:id',array('id'=>$id)); }
            else { $params['company']=$company; $params['actor']=$context['usuario_id']; $row=$this->db->execute('INSERT INTO sgpm.programacion_reportes(empresa_id,creado_por,formato,filtros,hora_local,estado) VALUES(:company,:actor,:format,:filters,:hour,:state) RETURNING *',$params)->fetch(); $id=(int)$row['id']; }
            foreach ($recipients as $recipient) { $this->db->execute('INSERT INTO sgpm.programacion_destinatarios(programacion_id,usuario_id) VALUES(:id,:user)',array('id'=>$id,'user'=>$recipient)); }
            return $this->dto($row);
        });
    }
    private static function invalid() { throw new \Sgpm\Http\HttpException(400,'PROGRAMACION_INVALIDA','Revisa hora, filtros y destinatarios de la programación.'); }
    public function delete(int $id,int $version,array $context)
    {
        (new ResourceAccess($this->db))->admin($context);
        Mutation::run($this->db,$context,function () use ($id,$version,$context) {
            (new ResourceAccess($this->db))->admin($context);
            $row=$this->db->execute('SELECT * FROM sgpm.programacion_reportes WHERE id=:id FOR UPDATE',array('id'=>$id))->fetch(); if (!$row) { ResourceAccess::missing(); } Mutation::version($row,array('version'=>$version));
            $this->db->execute('DELETE FROM sgpm.programacion_destinatarios WHERE programacion_id=:id',array('id'=>$id));
            $this->db->execute('DELETE FROM sgpm.programacion_reportes WHERE id=:id',array('id'=>$id));
        });
    }
}
