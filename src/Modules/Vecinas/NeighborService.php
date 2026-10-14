<?php
declare(strict_types=1);
namespace Sgpm\Modules\Vecinas;
use Sgpm\Infrastructure\Fields;
use Sgpm\Infrastructure\Mutation;

class NeighborService
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public static function fields(array $data): array
    {
        Fields::only($data,array('nombre','nit','observaciones','version'));
        return array('nombre'=>Fields::text($data,'nombre',255),'nit'=>Fields::text($data,'nit',50,false),'observaciones'=>Fields::text($data,'observaciones',100000,false));
    }
    public function save(int $id,array $data,array $context): array
    {
        (new \Sgpm\Auth\ResourceAccess($this->db))->admin($context);
        return Mutation::run($this->db,$context,function () use ($id,$data,$context) {
            (new \Sgpm\Auth\ResourceAccess($this->db))->admin($context); $repository=new NeighborRepository($this->db);
            $old=$repository->find($id,true); Mutation::version($old,$data);
            Fields::only($data,array('nombre','nit','observaciones','version'));
            unset($old['id']); $fields=self::fields(array_merge($old,$data)); $fields['id']=$id;
            $this->db->execute('UPDATE sgpm.empresa_vecina SET nombre=:nombre,nit=:nit,observaciones=:observaciones,version=version+1 WHERE id=:id',$fields);
            return $repository->find($id);
        });
    }
    public function delete(int $id,int $version,array $context)
    {
        (new \Sgpm\Auth\ResourceAccess($this->db))->admin($context);
        Mutation::run($this->db,$context,function () use ($id,$version,$context) {
            (new \Sgpm\Auth\ResourceAccess($this->db))->admin($context);
            Mutation::version((new NeighborRepository($this->db))->find($id,true),array('version'=>$version));
            $this->db->execute('DELETE FROM sgpm.empresa_vecina WHERE id=:id',array('id'=>$id));
        });
    }
}
