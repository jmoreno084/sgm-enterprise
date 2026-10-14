<?php
declare(strict_types=1);
namespace Sgpm\Modules\Vecinas;
use Sgpm\Infrastructure\Fields;
use Sgpm\Infrastructure\Mutation;
use Sgpm\Auth\ResourceAccess;

class ProjectNeighborService
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function resolve(int $project,string $nit,array $context): array
    {
        (new ResourceAccess($this->db))->project($project,'editar',$context);
        return (new NeighborRepository($this->db))->resolve(Fields::text(array('nit'=>$nit),'nit',50));
    }
    public function save(int $project,int $neighbor,array $data,array $context): array
    {
        return Mutation::run($this->db,$context,function () use ($project,$neighbor,$data,$context) {
            $this->db->execute('SELECT id FROM sgpm.proyecto WHERE id=:id FOR UPDATE',array('id'=>$project));
            $projectRow=(new ResourceAccess($this->db))->project($project,'editar',$context);
            $repository=new ProjectNeighborRepository($this->db);
            if ($neighbor) {
                Fields::only($data,array('observaciones','version'));
                Mutation::version($repository->find($project,$neighbor),$data);
                $this->db->execute('UPDATE sgpm.proyecto_empresas_vecinas SET observaciones=:notes,version=version+1 WHERE proyecto_id=:project AND empresa_vecina_id=:neighbor',array('notes'=>Fields::text($data,'observaciones',100000,false),'project'=>$project,'neighbor'=>$neighbor));
            } else {
                Fields::only($data,array('empresa_vecina_id','nueva_identidad','observaciones'));
                $existing=array_key_exists('empresa_vecina_id',$data); $new=array_key_exists('nueva_identidad',$data);
                if ($existing===$new) { throw new \Sgpm\Http\HttpException(400,'IDENTIDAD_AMBIGUA','Selecciona una identidad existente o crea una nueva, exclusivamente.'); }
                if ($existing) {
                    if (!is_int($data['empresa_vecina_id']) || $data['empresa_vecina_id']<1) { throw new \Sgpm\Http\HttpException(400,'VECINA_INVALIDA','La identidad no es válida.'); }
                    $neighbor=$data['empresa_vecina_id']; (new NeighborRepository($this->db))->find($neighbor);
                } else {
                    if (!is_array($data['nueva_identidad'])) { throw new \Sgpm\Http\HttpException(400,'VECINA_INVALIDA','Completa la nueva identidad.'); }
                    $identity=(new NeighborRepository($this->db))->insert(NeighborService::fields($data['nueva_identidad'])); $neighbor=$identity['id'];
                }
                $ordinal=(int)$projectRow['vecina_secuencia']+1;
                $style=NeighborStylePolicy::assign($ordinal,$repository->usedColors($project));
                $this->db->execute('UPDATE sgpm.proyecto SET vecina_secuencia=:ordinal,version=version+1,modificado_por=:actor WHERE id=:id',array('ordinal'=>$ordinal,'actor'=>(int)$context['usuario_id'],'id'=>$project));
                $this->db->execute('INSERT INTO sgpm.proyecto_empresas_vecinas(proyecto_id,empresa_vecina_id,color_hex,orden_registro,patron_indice,observaciones) VALUES(:project,:neighbor,:color,:ordinal,:pattern,:notes)',
                    array('project'=>$project,'neighbor'=>$neighbor,'color'=>$style['color_hex'],'ordinal'=>$ordinal,'pattern'=>$style['patron_indice'],'notes'=>Fields::text($data,'observaciones',100000,false)));
            }
            return $repository->find($project,$neighbor);
        });
    }
    public function delete(int $project,int $neighbor,int $version,array $context)
    {
        Mutation::run($this->db,$context,function () use ($project,$neighbor,$version,$context) {
            $this->db->execute('SELECT id FROM sgpm.proyecto WHERE id=:id FOR UPDATE',array('id'=>$project));
            (new ResourceAccess($this->db))->project($project,'editar',$context);
            Mutation::version((new ProjectNeighborRepository($this->db))->find($project,$neighbor),array('version'=>$version));
            $this->db->execute('DELETE FROM sgpm.proyecto_empresas_vecinas WHERE proyecto_id=:project AND empresa_vecina_id=:neighbor',array('project'=>$project,'neighbor'=>$neighbor));
        });
    }
}
