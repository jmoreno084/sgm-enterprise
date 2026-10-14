<?php
declare(strict_types=1);
namespace Sgpm\Modules\Proyectos;
use Sgpm\Infrastructure\Fields;

class ProjectRepository
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function find(int $id): array
    {
        $row=$this->db->execute('SELECT p.*,public.ST_AsGeoJSON(centroide) AS centroide_json FROM sgpm.proyecto p WHERE id=:id',array('id'=>$id))->fetch();
        if (!$row) { \Sgpm\Auth\ResourceAccess::missing(); }
        return self::dto($row);
    }
    public static function dto(array $row): array
    {
        $row=Fields::row($row); $row['centroide']=isset($row['centroide_json'])?json_decode($row['centroide_json'],true):null;
        unset($row['centroide_json']); return $row;
    }
    public function listing(int $company,array $query): array
    {
        $page=Fields::page($query); $params=array('empresa'=>$company); $where='empresa_id=:empresa';
        foreach (array('nombre','estado','mineral_principal') as $key) {
            if (isset($query[$key]) && $query[$key]!=='') { $where.=' AND '.$key.($key==='estado'?' = :':' ILIKE :').$key; $params[$key]=$key==='estado'?Fields::state($query):'%'.Fields::text($query,$key,255).'%'; }
        }
        $total=(int)$this->db->execute('SELECT count(*) FROM sgpm.proyecto WHERE '.$where,$params)->fetchColumn();
        $params['limit']=$page['page_size']; $params['offset']=$page['offset'];
        $rows=$this->db->execute('SELECT *,public.ST_AsGeoJSON(centroide) AS centroide_json FROM sgpm.proyecto WHERE '.$where.' ORDER BY id LIMIT :limit OFFSET :offset',$params)->fetchAll();
        return array('items'=>array_map(array(__CLASS__,'dto'),$rows),'total'=>$total,'page'=>$page['page'],'page_size'=>$page['page_size']);
    }
    public function save(int $company,int $id,array $data,int $actor): array
    {
        $keys=array_keys($data);
        if ($id) {
            $set=array(); foreach ($keys as $key) { $set[]=$key.'=:'.$key; }
            $data['id']=$id; $data['actor']=$actor;
            $this->db->execute('UPDATE sgpm.proyecto SET '.implode(',',$set).',modificado_por=:actor,version=version+1 WHERE id=:id',$data);
        } else {
            $data['empresa_id']=$company; $data['creado_por']=$actor; $data['modificado_por']=$actor; $keys=array_keys($data);
            $id=(int)$this->db->execute('INSERT INTO sgpm.proyecto('.implode(',',$keys).') VALUES(:'.implode(',:',$keys).') RETURNING id',$data)->fetchColumn();
        }
        return $this->find($id);
    }
}
