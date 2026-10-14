<?php
declare(strict_types=1);
namespace Sgpm\Modules\Vecinas;
use Sgpm\Infrastructure\Fields;

class NeighborRepository
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function find(int $id,bool $lock=false): array
    {
        $row=$this->db->execute('SELECT * FROM sgpm.empresa_vecina WHERE id=:id'.($lock?' FOR UPDATE':''),array('id'=>$id))->fetch();
        if (!$row) { \Sgpm\Auth\ResourceAccess::missing(); }
        return Fields::row($row);
    }
    public function resolve(string $nit): array
    {
        $row=$this->db->execute('SELECT id,nombre,nit FROM sgpm.empresa_vecina WHERE nit=:nit',array('nit'=>trim($nit)))->fetch();
        if (!$row) { \Sgpm\Auth\ResourceAccess::missing(); }
        return Fields::row($row);
    }
    public function insert(array $fields): array
    {
        $row=$this->db->execute('INSERT INTO sgpm.empresa_vecina(nombre,nit,observaciones) VALUES(:nombre,:nit,:observaciones) RETURNING id',$fields)->fetch();
        return $this->find((int)$row['id']);
    }
    public function listing(array $query): array
    {
        $page=Fields::page($query); $where=array('TRUE'); $params=array();
        foreach (array('nombre','nit') as $key) { if (isset($query[$key]) && $query[$key]!=='') { $where[]=$key.' ILIKE :'.$key; $params[$key]='%'.Fields::text($query,$key,255).'%'; } }
        $sql=implode(' AND ',$where); $total=(int)$this->db->execute('SELECT count(*) FROM sgpm.empresa_vecina WHERE '.$sql,$params)->fetchColumn();
        $params['limit']=$page['page_size']; $params['offset']=$page['offset'];
        return array('items'=>array_map(array(Fields::class,'row'),$this->db->execute('SELECT * FROM sgpm.empresa_vecina WHERE '.$sql.' ORDER BY id LIMIT :limit OFFSET :offset',$params)->fetchAll()),'total'=>$total,'page'=>$page['page'],'page_size'=>$page['page_size']);
    }
}
