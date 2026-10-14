<?php
declare(strict_types=1);
namespace Sgpm\Modules\Catastro;

class CadastreSchema
{
    private $db;
    private $cache=array();
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public static function attributes(): array
    {
        return array('CODIGO_EXPEDIENTE','MODALIDAD','TITULO_ESTADO','AREA_HA','CLASIFICACION_MINERIA','ETAPA','SOLICITANTES_O_TITULARES','MINERALES',
            'FECHA_DE_SOLICITUD','FECHA_DE_EXPEDICION','FECHA_DE_ANIVERSARIO','FECHA_DE_EXPIRACION','PUBLICADO_EN_RUCOM','PAR','TITLE_TYPE_CODE',
            'TENURE_STATUS_CODE','MINING_CLASSIFICATION_CODE','TENURE_STAGE_CODE','CENTROID_COORDINATE','TENURE_ID','OBJECTID','MUNICIPIOS','DEPARTAMENTOS',
            'MINERALES_INACTIVOS','TIPO_TERMINACION','TERMINATION_TYPE_CODE','ACTIVE_TENURE_STATUS_IND','ACTIVE_APPLICATION_STATUS_IND','FECHA_PROCESO','the_geom');
    }
    public static function table(string $type): string { return \Sgpm\Modules\Proyectos\ExpedienteRepository::table($type); }
    public static function quote(string $identifier): string { return '"'.str_replace('"','""',$identifier).'"'; }
    public function columns(string $type): array
    {
        if (isset($this->cache[$type])) { return $this->cache[$type]; }
        $table=self::table($type);
        $rows=$this->db->execute("SELECT a.attname,a.attnum,a.attnotnull,format_type(a.atttypid,a.atttypmod) AS datatype
            FROM pg_attribute a WHERE a.attrelid=CAST(:table AS regclass) AND a.attnum>0 AND NOT a.attisdropped ORDER BY a.attnum",array('table'=>$table))->fetchAll();
        $expected=array('character varying(100)','character varying(255)','character varying(100)','numeric(14,4)','character varying(100)','character varying(100)','text','text',
            'date','date','date','date','character varying(50)','character varying(100)','character varying(50)','character varying(50)','character varying(50)',
            'character varying(50)','character varying(100)','character varying(100)','integer','character varying(255)','character varying(255)','text','character varying(100)',
            'character varying(50)','character varying(10)','character varying(10)','date','geometry(MultiPolygon,4326)');
        $canonical=self::attributes(); $physical=array();
        foreach ($rows as $row) { $physical[strtoupper($row['attname'])]=$row; }
        if (count($rows)!==30) { $this->incompatible(); }
        $map=array();
        foreach ($canonical as $index=>$key) {
            $row=isset($physical[strtoupper($key)])?$physical[strtoupper($key)]:null;
            if (!$row || $row['datatype']!==$expected[$index] || ((($index===0 || $index===29)?true:false)!==(bool)$row['attnotnull'])) { $this->incompatible(); }
            $map[$key]=$row['attname'];
        }
        $code=(int)$physical['CODIGO_EXPEDIENTE']['attnum']; $geom=(int)$physical['THE_GEOM']['attnum'];
        $unique=$this->db->execute('SELECT EXISTS(SELECT 1 FROM pg_index i WHERE i.indrelid=CAST(:table AS regclass) AND i.indisunique AND i.indisvalid AND i.indpred IS NULL AND i.indkey::text=:code)',array('table'=>$table,'code'=>(string)$code))->fetchColumn();
        $gist=$this->db->execute("SELECT EXISTS(SELECT 1 FROM pg_index i JOIN pg_class c ON c.oid=i.indexrelid JOIN pg_am am ON am.oid=c.relam WHERE i.indrelid=CAST(:table AS regclass) AND i.indisvalid AND i.indpred IS NULL AND am.amname='gist' AND :geom=ANY(i.indkey))",array('table'=>$table,'geom'=>$geom))->fetchColumn();
        if (!$unique || !$gist) { $this->incompatible(); }
        $this->cache[$type]=$map; return $map;
    }
    public function column(string $type,string $canonical): string
    {
        $map=$this->columns($type);
        if (!isset($map[$canonical])) { throw new \InvalidArgumentException('Atributo canonico desconocido.'); }
        return self::quote($map[$canonical]);
    }
    private function incompatible() { throw new \Sgpm\Http\HttpException(503,'CATASTRO_INCOMPATIBLE','La fuente catastral no cumple el contrato requerido. Contacta al administrador.'); }
}
