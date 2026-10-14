<?php
declare(strict_types=1);
namespace Sgpm\Modules\Proyectos;
use Sgpm\Auth\ResourceAccess;
use Sgpm\Infrastructure\Fields;
use Sgpm\Infrastructure\Mutation;

class ProjectService
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function find(int $id,array $context): array
    {
        (new ResourceAccess($this->db))->project($id,'consultar',$context);
        return (new ProjectRepository($this->db))->find($id);
    }
    public function save(int $company,int $id,array $data,array $context): array
    {
        return Mutation::run($this->db,$context,function () use ($company,$id,$data,$context) {
            $access=new ResourceAccess($this->db); $repository=new ProjectRepository($this->db); $old=array();
            if ($id) {
                $old=$this->db->execute('SELECT * FROM sgpm.proyecto WHERE id=:id FOR UPDATE',array('id'=>$id))->fetch();
                if (!$old) { ResourceAccess::missing(); }
                if ((int)$old['empresa_id']!==$company) { ResourceAccess::missing(); }
                $access->company($company,'editar',$context);
                Mutation::version($old,$data);
                if ($old['estado']!=='ACTIVO') { $access->admin($context); }
            }
            $access->company($company,'editar',$context);
            Fields::only($data,array('nombre','descripcion','estado','mineral_principal','fecha_inicio','fecha_fin','observaciones','version'));
            $merged=array_merge($old,$data); $fields=array('nombre'=>Fields::text($merged,'nombre',255),'mineral_principal'=>Fields::text($merged,'mineral_principal',100),'estado'=>Fields::state($merged));
            foreach (array('descripcion','observaciones') as $key) { $fields[$key]=Fields::text($merged,$key,100000,false); }
            foreach (array('fecha_inicio','fecha_fin') as $key) { $fields[$key]=Fields::date($merged,$key); }
            if ($fields['fecha_inicio']!==null && $fields['fecha_fin']!==null && $fields['fecha_fin']<$fields['fecha_inicio']) { throw new \Sgpm\Http\HttpException(400,'FECHAS_INVALIDAS','La fecha final debe ser posterior o igual a la inicial.'); }
            return $repository->save($company,$id,$fields,(int)$context['usuario_id']);
        });
    }
    public function delete(int $id,int $version,array $context)
    {
        Mutation::run($this->db,$context,function () use ($id,$version,$context) {
            $this->db->execute('SELECT id FROM sgpm.proyecto WHERE id=:id FOR UPDATE',array('id'=>$id));
            $row=(new ResourceAccess($this->db))->project($id,'editar',$context);
            Mutation::version($row,array('version'=>$version));
            $this->db->execute('DELETE FROM sgpm.proyecto WHERE id=:id',array('id'=>$id));
        });
    }
}
