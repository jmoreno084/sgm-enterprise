<?php
declare(strict_types=1);
namespace Sgpm\Modules\Empresas;
use Sgpm\Infrastructure\Fields;

class CompanyRepository
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function find(int $id,bool $lock=false): array
    {
        $row=$this->db->execute('SELECT * FROM sgpm.empresa WHERE id=:id'.($lock?' FOR UPDATE':''),array('id'=>$id))->fetch();
        if (!$row) { \Sgpm\Auth\ResourceAccess::missing(); }
        return self::dto($row);
    }
    public static function dto(array $row): array
    {
        $row=Fields::row($row);
        $row['logo_url']=$row['logo_url']===null?null:'/api/v1/empresas/'.$row['id'].'/logo';
        return $row;
    }
    public function listing(array $actor,array $query): array
    {
        $page=Fields::page($query); $params=array(); $where=array('TRUE');
        if (!\Sgpm\Auth\Authorization::permits($actor,'admin')) {
            $where[]="EXISTS(SELECT 1 FROM sgpm.empresa_usuarios m WHERE m.empresa_id=e.id AND m.usuario_id=:actor AND m.estado='ACTIVO')";
            $params['actor']=(int)$actor['id'];
        }
        foreach (array('nit','razon_social','estado') as $key) {
            if (isset($query[$key]) && $query[$key]!=='') { $where[]='e.'.$key.($key==='estado'?' = :':' ILIKE :').$key; $params[$key]=$key==='estado'?Fields::state($query):'%'.Fields::text($query,$key,255).'%'; }
        }
        $sql=implode(' AND ',$where);
        $total=(int)$this->db->execute('SELECT count(*) FROM sgpm.empresa e WHERE '.$sql,$params)->fetchColumn();
        $params['limit']=$page['page_size']; $params['offset']=$page['offset'];
        $rows=$this->db->execute('SELECT e.* FROM sgpm.empresa e WHERE '.$sql.' ORDER BY e.id LIMIT :limit OFFSET :offset',$params)->fetchAll();
        return array('items'=>array_map(array(__CLASS__,'dto'),$rows),'total'=>$total,'page'=>$page['page'],'page_size'=>$page['page_size']);
    }
    public function save(int $id,array $data): array
    {
        $keys=array_keys($data);
        if ($id) {
            $set=array(); foreach ($keys as $key) { $set[]=$key.'=:'.$key; }
            $data['id']=$id;
            $this->db->execute('UPDATE sgpm.empresa SET '.implode(',',$set).',version=version+1 WHERE id=:id',$data);
        } else { $id=(int)$this->db->execute('INSERT INTO sgpm.empresa('.implode(',',$keys).') VALUES(:'.implode(',:',$keys).') RETURNING id',$data)->fetchColumn(); }
        return $this->find($id);
    }
}
