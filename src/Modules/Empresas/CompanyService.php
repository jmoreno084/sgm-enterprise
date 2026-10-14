<?php
declare(strict_types=1);
namespace Sgpm\Modules\Empresas;
use Sgpm\Infrastructure\Fields;
use Sgpm\Infrastructure\Mutation;
use Sgpm\Auth\ResourceAccess;

class CompanyService
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function find(int $id,array $context): array
    {
        (new ResourceAccess($this->db))->company($id,'consultar',$context);
        return (new CompanyRepository($this->db))->find($id);
    }
    public function save(int $id,array $data,array $context): array
    {
        (new ResourceAccess($this->db))->admin($context);
        Fields::only($data,array('nit','razon_social','correo','telefono','direccion','ciudad','departamento','estado','version'));
        return Mutation::run($this->db,$context,function () use ($id,$data,$context) {
            (new ResourceAccess($this->db))->admin($context);
            $repository=new CompanyRepository($this->db); $old=array();
            if ($id) { $old=$repository->find($id,true); Mutation::version($old,$data); }
            $merged=array_merge($old,$data); $fields=array();
            foreach (array('nit'=>50,'razon_social'=>255,'correo'=>255,'ciudad'=>100,'departamento'=>100) as $key=>$size) { $fields[$key]=Fields::text($merged,$key,$size); }
            if (!filter_var($fields['correo'],FILTER_VALIDATE_EMAIL)) { throw new \Sgpm\Http\HttpException(400,'CORREO_INVALIDO','El correo no es válido.'); }
            foreach (array('telefono'=>50,'direccion'=>255) as $key=>$size) { $fields[$key]=Fields::text($merged,$key,$size,false); }
            $fields['estado']=Fields::state($merged);
            return $repository->save($id,$fields);
        });
    }
    public function delete(int $id,int $version,array $context)
    {
        (new ResourceAccess($this->db))->admin($context);
        Mutation::run($this->db,$context,function () use ($id,$version,$context) {
            (new ResourceAccess($this->db))->admin($context);
            Mutation::version((new CompanyRepository($this->db))->find($id,true),array('version'=>$version));
            $this->db->execute('DELETE FROM sgpm.empresa WHERE id=:id',array('id'=>$id));
        });
    }
}
