<?php
declare(strict_types=1);
namespace Sgpm\Modules\Visor;
use Sgpm\Infrastructure\Fields;

class MapRepository
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public static function bbox(string $value): array
    {
        $parts=explode(',',$value);
        if (count($parts)!==4) { self::invalidBbox(); }
        $numbers=array(); foreach ($parts as $part) { if (!is_numeric($part) || !is_finite((float)$part)) { self::invalidBbox(); } $numbers[]=(float)$part; }
        if ($numbers[0]<-180 || $numbers[2]>180 || $numbers[1]<-90 || $numbers[3]>90 || $numbers[0]>=$numbers[2] || $numbers[1]>=$numbers[3]) { self::invalidBbox(); }
        return $numbers;
    }
    private static function invalidBbox() { throw new \Sgpm\Http\HttpException(400,'BBOX_INVALIDO','Indica un rectángulo válido en coordenadas WGS84.'); }
    public function features(int $project,array $query,array $context,bool $includeArea=false): array
    {
        $p=(new \Sgpm\Auth\ResourceAccess($this->db))->project($project,'consultar',$context);
        $schema=new \Sgpm\Modules\Catastro\CadastreSchema($this->db);
        $tcode=$schema->column('TITULO','CODIGO_EXPEDIENTE'); $scode=$schema->column('SOLICITUD','CODIGO_EXPEDIENTE');
        $geometry="CASE WHEN e.tipo_expediente='TITULO' THEN t.".$schema->column('TITULO','the_geom').' ELSE s.'.$schema->column('SOLICITUD','the_geom').' END';
        $area="CASE WHEN e.tipo_expediente='TITULO' THEN t.".$schema->column('TITULO','AREA_HA').' ELSE s.'.$schema->column('SOLICITUD','AREA_HA').' END';
        $joins=" LEFT JOIN public.geo_titulos_mineros t ON e.tipo_expediente='TITULO' AND t.$tcode=e.codigo_expediente
            LEFT JOIN public.geo_solicitudes_mineras s ON e.tipo_expediente='SOLICITUD' AND s.$scode=e.codigo_expediente
            LEFT JOIN sgpm.proyecto_empresas_vecinas v ON v.proyecto_id=e.proyecto_id AND v.empresa_vecina_id=e.empresa_vecina_id
            LEFT JOIN sgpm.empresa_vecina n ON n.id=e.empresa_vecina_id";
        $from=' FROM sgpm.proyecto_expedientes e'.$joins.' WHERE e.proyecto_id=:project';
        $params=array('project'=>$project);
        foreach (array('tipo'=>'tipo_expediente','filiacion'=>'filiacion') as $key=>$column) {
            if (isset($query[$key]) && $query[$key]!=='') {
                $valid=$key==='tipo'?array('TITULO','SOLICITUD'):array('PROPIO','VECINO');
                if (!in_array($query[$key],$valid,true)) { throw new \Sgpm\Http\HttpException(400,'FILTRO_INVALIDO','Revisa los filtros del mapa.'); }
                $from.=' AND e.'.$column.'=:'.$key; $params[$key]=$query[$key];
            }
        }
        if (isset($query['bbox']) && $query['bbox']!=='') {
            $bbox=self::bbox(Fields::text($query,'bbox',200));
            foreach (array('minlon','minlat','maxlon','maxlat') as $index=>$key) { $params[$key]=$bbox[$index]; }
            $from.=' AND (('.$geometry.') IS NULL OR public.ST_Intersects(('.$geometry.'),public.ST_MakeEnvelope(:minlon,:minlat,:maxlon,:maxlat,4326)))';
        }
        $number=isset($query['page'])?filter_var($query['page'],FILTER_VALIDATE_INT):1;
        $size=isset($query['page_size'])?filter_var($query['page_size'],FILTER_VALIDATE_INT):100;
        if ($number===false || $number<1 || $number>1000000 || $size===false || $size<1 || $size>1000) {
            throw new \Sgpm\Http\HttpException(400,'CAMPOS_INVALIDOS','Revisa la paginación del mapa.');
        }
        $page=array('page'=>$number,'page_size'=>$size,'offset'=>($number-1)*$size);
        $total=(int)$this->db->execute('SELECT count(*)'.$from,$params)->fetchColumn();
        $params['limit']=$page['page_size']; $params['offset']=$page['offset'];
        // PostgreSQL9.5 otherwise projects/serializes every geometry before sorting
        // and LIMIT. Materialize the page first; preserve every source coordinate.
        $selection='SELECT e.*'.$from.' ORDER BY e.id LIMIT :limit OFFSET :offset';
        $areaSelect=$includeArea?',('.$area.') AS official_area,public.ST_Area(('.$geometry.')::public.geography)/10000.0 AS geodesic_area':',('.$area.') AS official_area,NULL AS geodesic_area';
        $sql='SELECT e.*,n.nombre AS empresa_vecina_nombre,v.color_hex,v.patron_indice,public.ST_AsGeoJSON(('.$geometry.'),15) AS geometry_json'.$areaSelect.' FROM ('.$selection.') e'.$joins.' ORDER BY e.id';
        $features=array(); $missing=array();
        foreach ($this->db->execute($sql,$params)->fetchAll() as $row) {
            if ($row['geometry_json']===null) { $missing[]=array('vinculo_id'=>(int)$row['id'],'codigo_expediente'=>$row['codigo_expediente'],'tipo_expediente'=>$row['tipo_expediente']); continue; }
            $properties=array('vinculo_id'=>(int)$row['id'],'codigo_expediente'=>$row['codigo_expediente'],'tipo_expediente'=>$row['tipo_expediente'],'filiacion'=>$row['filiacion'],
                'proyecto_id'=>$project,'proyecto_nombre'=>$p['nombre'],'empresa_vecina_id'=>$row['empresa_vecina_id']===null?null:(int)$row['empresa_vecina_id'],'empresa_vecina_nombre'=>$row['empresa_vecina_nombre'],
                'color_hex'=>$row['filiacion']==='PROPIO'?($row['tipo_expediente']==='TITULO'?'#007bff':'#28a745'):$row['color_hex'],'patron_indice'=>$row['filiacion']==='PROPIO'?0:(int)$row['patron_indice'],
                'tooltip'=>$row['filiacion']==='PROPIO'?'Proyecto: '.$p['nombre'].' ('.$row['codigo_expediente'].')':$row['empresa_vecina_nombre']);
            $properties=array_merge($properties,AreaService::value($row['official_area'],$row['geodesic_area']));
            $features[]=array('type'=>'Feature','id'=>(int)$row['id'],'geometry'=>json_decode($row['geometry_json'],true),'properties'=>$properties);
        }
        return array('type'=>'FeatureCollection','features'=>$features,'meta'=>array('page'=>$page['page'],'page_size'=>$page['page_size'],'total'=>$total,
            'next_page'=>$page['page']*$page['page_size']<$total?$page['page']+1:null,'fuentes_no_disponibles'=>$missing));
    }
}
