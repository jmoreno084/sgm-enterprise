<?php
declare(strict_types=1);
namespace Sgpm\Modules\Proyectos;
use Sgpm\Infrastructure\Fields;
use Sgpm\Infrastructure\Mutation;
use Sgpm\Auth\ResourceAccess;

class ExpedienteService
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function save(int $project,int $id,array $data,array $context): array
    {
        return Mutation::run($this->db,$context,function () use ($project,$id,$data,$context) {
            $this->db->execute('SELECT id FROM sgpm.proyecto WHERE id=:id FOR UPDATE',array('id'=>$project));
            (new ResourceAccess($this->db))->project($project,'editar',$context);
            Fields::only($data,$id?array('filiacion','empresa_vecina_id','version'):array('codigo_expediente','tipo_expediente','filiacion','empresa_vecina_id'));
            $repository=new ExpedienteRepository($this->db); $old=array();
            if ($id) { $old=$repository->find($project,$id); Mutation::version($old,$data); }
            $merged=array_merge($old,$data); $affiliation=isset($merged['filiacion'])?$merged['filiacion']:'';
            if (!in_array($affiliation,array('PROPIO','VECINO'),true)) { throw new \Sgpm\Http\HttpException(400,'FILIACION_INVALIDA','Selecciona la filiación del expediente.'); }
            $neighbor=isset($merged['empresa_vecina_id'])?$merged['empresa_vecina_id']:null;
            if (($affiliation==='PROPIO' && $neighbor!==null) || ($affiliation==='VECINO' && (!is_int($neighbor) || $neighbor<1))) { throw new \Sgpm\Http\HttpException(400,'VECINA_INVALIDA','Selecciona una vecina del mismo proyecto.'); }
            if ($neighbor!==null && !$this->db->execute('SELECT id FROM sgpm.proyecto_empresas_vecinas WHERE proyecto_id=:project AND empresa_vecina_id=:neighbor',array('project'=>$project,'neighbor'=>$neighbor))->fetch()) { throw new \Sgpm\Http\HttpException(400,'VECINA_INVALIDA','La vecina no está vinculada a este proyecto.'); }
            if ($id) {
                $this->db->execute('UPDATE sgpm.proyecto_expedientes SET filiacion=:affiliation,empresa_vecina_id=:neighbor,version=version+1 WHERE id=:id',array('affiliation'=>$affiliation,'neighbor'=>$neighbor,'id'=>$id));
                $result=$repository->find($project,$id);
            } else {
                $type=Fields::text($merged,'tipo_expediente',20); ExpedienteRepository::table($type);
                $result=$repository->insert($project,array('codigo_expediente'=>Fields::text($merged,'codigo_expediente',100),'tipo_expediente'=>$type,'filiacion'=>$affiliation,'empresa_vecina_id'=>$neighbor));
            }
            (new CentroidService($this->db))->refresh($project,(int)$context['usuario_id']);
            return $result;
        });
    }
    public function delete(int $project,int $id,int $version,array $context)
    {
        Mutation::run($this->db,$context,function () use ($project,$id,$version,$context) {
            $this->db->execute('SELECT id FROM sgpm.proyecto WHERE id=:id FOR UPDATE',array('id'=>$project));
            (new ResourceAccess($this->db))->project($project,'editar',$context);
            Mutation::version((new ExpedienteRepository($this->db))->find($project,$id),array('version'=>$version));
            $this->db->execute('DELETE FROM sgpm.proyecto_expedientes WHERE id=:id',array('id'=>$id));
            (new CentroidService($this->db))->refresh($project,(int)$context['usuario_id']);
        });
    }
}
