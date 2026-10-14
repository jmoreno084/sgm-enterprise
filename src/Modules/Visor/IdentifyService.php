<?php
declare(strict_types=1);
namespace Sgpm\Modules\Visor;

class IdentifyService
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function find(int $project,int $file,array $context): array
    {
        $p=(new \Sgpm\Auth\ResourceAccess($this->db))->project($project,'consultar',$context);
        $row=(new \Sgpm\Modules\Proyectos\ExpedienteRepository($this->db))->find($project,$file); $available=true;
        try { $attributes=(new \Sgpm\Modules\Catastro\CadastreRepository($this->db))->find($row['tipo_expediente'],$row['codigo_expediente'])['atributos']; }
        catch (\Sgpm\Http\HttpException $error) {
            if ($error->status()!==404) { throw $error; }
            $available=false; $attributes=array_fill_keys(\Sgpm\Modules\Catastro\CadastreSchema::attributes(),null);
        }
        $name=null;
        if ($row['empresa_vecina_id']!==null) { $name=$this->db->execute('SELECT nombre FROM sgpm.empresa_vecina WHERE id=:id',array('id'=>$row['empresa_vecina_id']))->fetchColumn(); }
        $geodesic=null;
        if ($available && $attributes['AREA_HA']===null) {
            $schema=new \Sgpm\Modules\Catastro\CadastreSchema($this->db); $type=$row['tipo_expediente'];
            $geodesic=$this->db->execute('SELECT public.ST_Area('.$schema->column($type,'the_geom').'::public.geography)/10000.0 FROM '.$schema::table($type).' WHERE '.$schema->column($type,'CODIGO_EXPEDIENTE').'=:code',array('code'=>$row['codigo_expediente']))->fetchColumn();
        }
        return array_merge(array('vinculo'=>$row,'proyecto_nombre'=>$p['nombre'],'empresa_vecina_nombre'=>$name,'fuente_disponible'=>$available,'atributos'=>$attributes),AreaService::value($attributes['AREA_HA'],$geodesic));
    }
}
