<?php
declare(strict_types=1);
namespace Sgpm\Modules\Catastro;
use Sgpm\Infrastructure\Fields;

class CadastreRepository
{
    private $db;
    private $schema;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; $this->schema=new CadastreSchema($db); }
    public function find(string $type,string $code): array
    {
        $columns=array();
        foreach (CadastreSchema::attributes() as $key) {
            $column=$this->schema->column($type,$key);
            $columns[]=($key==='the_geom'?'public.ST_AsGeoJSON('.$column.',15)':$column).' AS '.CadastreSchema::quote($key);
        }
        $row=$this->db->execute('SELECT '.implode(',',$columns).' FROM '.CadastreSchema::table($type).' WHERE '.$this->schema->column($type,'CODIGO_EXPEDIENTE').'=:code',array('code'=>$code))->fetch();
        if (!$row) { \Sgpm\Auth\ResourceAccess::missing(); }
        $row['the_geom']=json_decode($row['the_geom'],true);
        if ($row['OBJECTID']!==null) { $row['OBJECTID']=(int)$row['OBJECTID']; }
        return array('tipo'=>$type,'atributos'=>$row);
    }
    public function listing(array $query): array
    {
        $page=Fields::page($query); $types=array('TITULO','SOLICITUD');
        if (isset($query['tipo']) && $query['tipo']!=='') { CadastreSchema::table(Fields::text($query,'tipo',20)); $types=array($query['tipo']); }
        $parts=array(); $params=array();
        $filters=array('codigo'=>'CODIGO_EXPEDIENTE','titular'=>'SOLICITANTES_O_TITULARES','municipio'=>'MUNICIPIOS','departamento'=>'DEPARTAMENTOS','mineral'=>'MINERALES');
        foreach ($types as $index=>$type) {
            $selected=array("'".$type."'::text AS tipo");
            $selected[]=$this->schema->column($type,'CODIGO_EXPEDIENTE').' AS "CODIGO_EXPEDIENTE"';
            $where=array('TRUE');
            foreach ($filters as $key=>$column) {
                if (isset($query[$key]) && $query[$key]!=='') { $param='filter_'.$index.'_'.$key; $where[]=$this->schema->column($type,$column).' ILIKE :'.$param; $params[$param]='%'.Fields::text($query,$key,255).'%'; }
            }
            $parts[]='SELECT '.implode(',',$selected).' FROM '.CadastreSchema::table($type).' WHERE '.implode(' AND ',$where);
        }
        $union=implode(' UNION ALL ',$parts);
        $params['limit']=$page['page_size']; $params['offset']=$page['offset'];
        // Evaluate official filters once, materializing only narrow identity keys.
        // Count and page share that result; fetch display attributes after LIMIT.
        $display=array('slice.tipo','slice."CODIGO_EXPEDIENTE"','counts.total AS total_count');
        foreach (array('SOLICITANTES_O_TITULARES','MUNICIPIOS','DEPARTAMENTOS','MINERALES','AREA_HA') as $key) {
            $display[]="CASE WHEN slice.tipo='TITULO' THEN t.".$this->schema->column('TITULO',$key).' ELSE s.'.$this->schema->column('SOLICITUD',$key).' END AS '.CadastreSchema::quote($key);
        }
        $sql='WITH matched AS ('.$union.'), counts AS (SELECT count(*) AS total FROM matched), slice AS (SELECT * FROM matched ORDER BY tipo,"CODIGO_EXPEDIENTE" LIMIT :limit OFFSET :offset) SELECT '.implode(',',$display).
            ' FROM counts LEFT JOIN slice ON TRUE LEFT JOIN public.geo_titulos_mineros t ON slice.tipo=\'TITULO\' AND t.'.$this->schema->column('TITULO','CODIGO_EXPEDIENTE').'=slice."CODIGO_EXPEDIENTE"'.
            ' LEFT JOIN public.geo_solicitudes_mineras s ON slice.tipo=\'SOLICITUD\' AND s.'.$this->schema->column('SOLICITUD','CODIGO_EXPEDIENTE').'=slice."CODIGO_EXPEDIENTE" ORDER BY slice.tipo,slice."CODIGO_EXPEDIENTE"';
        $rows=$this->db->execute($sql,$params)->fetchAll(); $total=(int)$rows[0]['total_count'];
        if ($rows[0]['tipo']===null) { $rows=array(); }
        foreach ($rows as &$row) { unset($row['total_count']); } unset($row);
        return array('items'=>$rows,'total'=>$total,'page'=>$page['page'],'page_size'=>$page['page_size']);
    }
}
