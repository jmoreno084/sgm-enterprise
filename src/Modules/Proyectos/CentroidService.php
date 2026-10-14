<?php
declare(strict_types=1);
namespace Sgpm\Modules\Proyectos;

class CentroidService
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    // Called only inside the audited mutation transaction, with its project locked.
    public function refresh(int $project,int $actor)
    {
        $schema=new \Sgpm\Modules\Catastro\CadastreSchema($this->db);
        $tgeom=$schema->column('TITULO','the_geom'); $tcode=$schema->column('TITULO','CODIGO_EXPEDIENTE');
        $sgeom=$schema->column('SOLICITUD','the_geom'); $scode=$schema->column('SOLICITUD','CODIGO_EXPEDIENTE');
        $sql="WITH shapes AS (
            SELECT t.$tgeom AS the_geom FROM sgpm.proyecto_expedientes e JOIN public.geo_titulos_mineros t ON t.$tcode=e.codigo_expediente
             WHERE e.proyecto_id=:titulo AND e.filiacion='PROPIO' AND e.tipo_expediente='TITULO'
            UNION ALL
            SELECT s.$sgeom AS the_geom FROM sgpm.proyecto_expedientes e JOIN public.geo_solicitudes_mineras s ON s.$scode=e.codigo_expediente
             WHERE e.proyecto_id=:solicitud AND e.filiacion='PROPIO' AND e.tipo_expediente='SOLICITUD'
        ), point AS (SELECT public.ST_Transform(public.ST_Centroid(public.ST_UnaryUnion(public.ST_Collect(public.ST_Transform(the_geom,3116)))),4326) AS geom FROM shapes)
        UPDATE sgpm.proyecto SET centroide=(SELECT geom FROM point),modificado_por=:actor,version=version+1 WHERE id=:id";
        $this->db->execute($sql,array('titulo'=>$project,'solicitud'=>$project,'actor'=>$actor,'id'=>$project));
    }
}
