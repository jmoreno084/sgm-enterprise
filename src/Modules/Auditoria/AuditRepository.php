<?php
declare(strict_types=1);
namespace Sgpm\Modules\Auditoria;
use Sgpm\Infrastructure\Fields;
use Sgpm\Auth\ResourceAccess;

class AuditRepository
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    private function authorize(int $company,array $context)
    {
        $access=new ResourceAccess($this->db);
        if ($company) { $access->company($company,'auditoria',$context); }
        else { $access->admin($context); }
    }
    public static function dto(array $row): array
    {
        foreach (array('id','usuario_id','entidad_id','empresa_id_contexto','proyecto_id_contexto') as $key) { if ($row[$key]!==null) { $row[$key]=(int)$row[$key]; } }
        foreach (array('valores_anteriores','valores_nuevos') as $key) { $row[$key]=$row[$key]===null?null:json_decode($row[$key],true); }
        return $row;
    }
    public function find(int $company,int $id,array $context): array
    {
        $this->authorize($company,$context);
        $params=array('id'=>$id); $where='id=:id';
        if ($company) { $where.=' AND empresa_id_contexto=:company'; $params['company']=$company; }
        $row=$this->db->execute('SELECT * FROM sgpm.auditoria WHERE '.$where,$params)->fetch();
        if (!$row) { ResourceAccess::missing(); }
        return self::dto($row);
    }
    public function listing(int $company,array $query,array $context): array
    {
        $this->authorize($company,$context); $page=Fields::page($query); $where=array('TRUE'); $params=array();
        if ($company) { $where[]='empresa_id_contexto=:company'; $params['company']=$company; }
        foreach (array('accion'=>50,'entidad'=>100) as $key=>$size) {
            if (isset($query[$key]) && $query[$key]!=='') { $where[]=$key.'=:'.$key; $params[$key]=Fields::text($query,$key,$size); }
        }
        if (isset($query['usuario_id']) && $query['usuario_id']!=='') {
            $id=filter_var($query['usuario_id'],FILTER_VALIDATE_INT);
            if ($id===false || $id<1) { throw new \Sgpm\Http\HttpException(400,'USUARIO_INVALIDO','Indica un ID de usuario válido.'); }
            $where[]='usuario_id=:actor'; $params['actor']=$id;
        }
        foreach (array('fecha_desde','fecha_hasta') as $key) {
            if (isset($query[$key]) && $query[$key]!=='') {
                $params[$key]=Fields::date($query,$key);
                $where[]=$key==='fecha_desde'?'fecha>=CAST(:fecha_desde AS date)':"fecha<CAST(:fecha_hasta AS date)+interval '1 day'";
            }
        }
        if (isset($params['fecha_desde'],$params['fecha_hasta']) && $params['fecha_hasta']<$params['fecha_desde']) { throw new \Sgpm\Http\HttpException(400,'FECHAS_INVALIDAS','Revisa el rango de fechas.'); }
        $sql=implode(' AND ',$where); $total=(int)$this->db->execute('SELECT count(*) FROM sgpm.auditoria WHERE '.$sql,$params)->fetchColumn();
        $params['limit']=$page['page_size']; $params['offset']=$page['offset'];
        $rows=$this->db->execute('SELECT * FROM sgpm.auditoria WHERE '.$sql.' ORDER BY fecha DESC,id DESC LIMIT :limit OFFSET :offset',$params)->fetchAll();
        return array('items'=>array_map(array(__CLASS__,'dto'),$rows),'total'=>$total,'page'=>$page['page'],'page_size'=>$page['page_size']);
    }
}
