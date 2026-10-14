<?php
declare(strict_types=1);
namespace Sgpm\Modules\Proyectos;
use Sgpm\Infrastructure\Fields;

class ExpedienteRepository
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public static function table(string $type): string
    {
        $tables=array('TITULO'=>'public.geo_titulos_mineros','SOLICITUD'=>'public.geo_solicitudes_mineras');
        if (!isset($tables[$type])) { throw new \Sgpm\Http\HttpException(400,'TIPO_INVALIDO','Selecciona TÍTULO o SOLICITUD.'); }
        return $tables[$type];
    }
    public function find(int $project,int $id): array
    {
        $row=$this->db->execute('SELECT * FROM sgpm.proyecto_expedientes WHERE id=:id AND proyecto_id=:project',array('id'=>$id,'project'=>$project))->fetch();
        if (!$row) { \Sgpm\Auth\ResourceAccess::missing(); }
        return Fields::row($row);
    }
    public function listing(int $project,array $query): array
    {
        $page=Fields::page($query); $params=array('project'=>$project); $where='e.proyecto_id=:project';
        foreach (array('tipo_expediente','filiacion','empresa_vecina_id') as $key) {
            if (isset($query[$key]) && $query[$key]!=='') { $where.=' AND e.'.$key.'=:'.$key; $params[$key]=Fields::text($query,$key,100); }
        }
        $total=(int)$this->db->execute('SELECT count(*) FROM sgpm.proyecto_expedientes e WHERE '.$where,$params)->fetchColumn();
        $params['limit']=$page['page_size']; $params['offset']=$page['offset'];
        $schema=new \Sgpm\Modules\Catastro\CadastreSchema($this->db); $tcode=$schema->column('TITULO','CODIGO_EXPEDIENTE'); $scode=$schema->column('SOLICITUD','CODIGO_EXPEDIENTE');
        $sql="SELECT e.*,CASE WHEN e.tipo_expediente='TITULO' THEN EXISTS(SELECT 1 FROM public.geo_titulos_mineros t WHERE t.$tcode=e.codigo_expediente)
            ELSE EXISTS(SELECT 1 FROM public.geo_solicitudes_mineras s WHERE s.$scode=e.codigo_expediente) END AS fuente_disponible
            FROM sgpm.proyecto_expedientes e WHERE ".$where.' ORDER BY e.id LIMIT :limit OFFSET :offset';
        return array('items'=>array_map(array(Fields::class,'row'),$this->db->execute($sql,$params)->fetchAll()),'total'=>$total,'page'=>$page['page'],'page_size'=>$page['page_size']);
    }
    public function insert(int $project,array $fields): array
    {
        $table=self::table($fields['tipo_expediente']); $fields['project']=$project;
        $code=(new \Sgpm\Modules\Catastro\CadastreSchema($this->db))->column($fields['tipo_expediente'],'CODIGO_EXPEDIENTE');
        $row=$this->db->execute('INSERT INTO sgpm.proyecto_expedientes(proyecto_id,codigo_expediente,tipo_expediente,filiacion,empresa_vecina_id)
            SELECT :project,:codigo_expediente,:tipo_expediente,:filiacion,:empresa_vecina_id FROM '.$table.' WHERE '.$code.'=:source_code RETURNING id',
            array_merge($fields,array('source_code'=>$fields['codigo_expediente'])))->fetch();
        if (!$row) { throw new \Sgpm\Http\HttpException(400,'FUENTE_NO_DISPONIBLE','El expediente no existe en la fuente del tipo indicado.'); }
        return $this->find($project,(int)$row['id']);
    }
}
