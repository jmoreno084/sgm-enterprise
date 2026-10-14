<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
use Sgpm\Auth\ResourceAccess;
use Sgpm\Infrastructure\Fields;

class ExportRepository
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public static function format(string $value): string
    {
        if (!in_array($value,array('PDF','XLSX','CSV','GEOJSON','KML','SHAPEFILE'),true)) { self::invalid(); }
        return $value;
    }
    private static function invalid() { throw new \Sgpm\Http\HttpException(400,'FILTROS_INVALIDOS','Revisa el formato y los filtros de exportación.'); }
    public static function filters(array $data): array
    {
        Fields::only($data,array('proyecto_id','codigos','tipo','filiacion','titular','municipio','departamento','mineral'));
        $result=array();
        if (isset($data['proyecto_id'])) {
            $id=filter_var($data['proyecto_id'],FILTER_VALIDATE_INT); if (!$id || $id<1) { self::invalid(); } $result['proyecto_id']=$id;
        }
        foreach (array('tipo','filiacion') as $key) {
            if (isset($data[$key]) && $data[$key]!=='') {
                if (!in_array($data[$key],$key==='tipo'?array('TITULO','SOLICITUD'):array('PROPIO','VECINO'),true)) { self::invalid(); } $result[$key]=$data[$key];
            }
        }
        foreach (array('titular','municipio','departamento','mineral') as $key) { if (isset($data[$key]) && $data[$key]!=='') { $result[$key]=Fields::text($data,$key,255); } }
        if (isset($data['codigos'])) {
            if (!is_array($data['codigos']) || count($data['codigos'])>1000) { self::invalid(); }
            $codes=array(); foreach ($data['codigos'] as $value) { $codes[]=Fields::text(array('code'=>$value),'code',255); }
            $result['codigos']=array_values(array_unique($codes));
        }
        return $result;
    }
    public function create(int $company,array $data,array $context): array
    {
        (new ResourceAccess($this->db))->company($company,'exportar',$context);
        Fields::only($data,array('formato','proyecto_id','filtros'));
        $format=self::format(Fields::text($data,'formato',20));
        if (isset($data['filtros']) && !is_array($data['filtros'])) { self::invalid(); }
        $filters=self::filters(isset($data['filtros'])?$data['filtros']:array());
        if (isset($data['proyecto_id'])) {
            if (isset($filters['proyecto_id']) && $filters['proyecto_id']!==$data['proyecto_id']) { self::invalid(); }
            $filters=self::filters(array_merge($filters,array('proyecto_id'=>$data['proyecto_id'])));
        }
        $this->scope($company,$filters,$context);
        $row=$this->db->execute('INSERT INTO sgpm.exportacion_job(usuario_id,empresa_id,proyecto_id,formato,filtros) VALUES(:usuario,:empresa,:proyecto,:formato,:filtros) RETURNING *',
            array('usuario'=>$context['usuario_id'],'empresa'=>$company,'proyecto'=>isset($filters['proyecto_id'])?$filters['proyecto_id']:null,'formato'=>$format,'filtros'=>json_encode((object)$filters)))->fetch();
        return self::publicRow($row);
    }
    private function scope(int $company,array $filters,array $context)
    {
        $access=new ResourceAccess($this->db); $access->company($company,'exportar',$context);
        if (isset($filters['proyecto_id'])) {
            $project=$access->project($filters['proyecto_id'],'exportar',$context);
            if ((int)$project['empresa_id']!==$company) { ResourceAccess::missing(); }
        }
    }
    public function internal(int $id): array
    {
        $row=$this->db->execute('SELECT * FROM sgpm.exportacion_job WHERE id=:id',array('id'=>$id))->fetch();
        if (!$row) { ResourceAccess::missing(); } return $row;
    }
    public function authorize(array $row,array $context)
    {
        $access=new ResourceAccess($this->db); $actor=$access->actor($context);
        if ((int)$row['usuario_id']!==(int)$actor['id'] && !\Sgpm\Auth\Authorization::permits($actor,'admin')) { ResourceAccess::missing(); }
        $this->scope((int)$row['empresa_id'],json_decode($row['filtros'],true),$context);
    }
    public function find(int $id,array $context): array { $row=$this->internal($id); $this->authorize($row,$context); return self::publicRow($row); }
    public static function publicRow(array $row): array
    {
        unset($row['archivo_relativo'],$row['procesando_en']);
        $row=Fields::row($row); $row['filtros']=json_decode($row['filtros'],true);
        $row['url_estado']='/api/v1/exportaciones/'.$row['id']; $row['url_descarga']=$row['url_estado'].'/archivo';
        return $row;
    }
    public static function directory(): string
    {
        $path=dirname(__DIR__,3).'/storage/exports';
        if (!is_dir($path) && !mkdir($path,0700,true) && !is_dir($path)) { throw new \RuntimeException('Almacenamiento privado no disponible.'); }
        return $path;
    }
    public static function path(string $relative): string
    {
        if (!preg_match('/^[a-f0-9]{64}\.(pdf|xlsx|csv|geojson|kml|zip)$/D',$relative)) { throw new \RuntimeException('Ruta privada inválida.'); }
        return self::directory().'/'.$relative;
    }
    public function download(int $id,array $context): array
    {
        $row=$this->internal($id); $this->authorize($row,$context);
        if ($row['estado']==='EXPIRADO' || strtotime($row['expira_en'].' UTC')<=time()) { throw new \Sgpm\Http\HttpException(410,'EXPORTACION_EXPIRADA','La exportación ha caducado. Solicita una nueva.'); }
        if ($row['estado']!=='LISTO' || !$row['archivo_relativo']) { throw new \Sgpm\Http\HttpException(409,'EXPORTACION_NO_LISTA','La exportación todavía no está disponible.'); }
        $path=self::path($row['archivo_relativo']);
        if (!is_file($path)) { throw new \Sgpm\Http\HttpException(410,'ARCHIVO_NO_DISPONIBLE','Solicita una nueva exportación.'); }
        return array('path'=>$path,'formato'=>$row['formato'],'id'=>$id);
    }
    public function records(array $job,array $context): \Generator
    {
        $this->authorize($job,$context); $filters=json_decode($job['filtros'],true);
        $after=0; $schema=new \Sgpm\Modules\Catastro\CadastreSchema($this->db);
        $cadastre=new \Sgpm\Modules\Catastro\CadastreRepository($this->db);
        do {
            $params=array('company'=>(int)$job['empresa_id'],'after'=>$after);
            $sql='SELECT e.*,p.nombre AS proyecto_nombre FROM sgpm.proyecto_expedientes e JOIN sgpm.proyecto p ON p.id=e.proyecto_id WHERE p.empresa_id=:company AND e.id>:after';
            foreach (array('proyecto_id'=>'e.proyecto_id','tipo'=>'e.tipo_expediente','filiacion'=>'e.filiacion') as $key=>$column) {
                if (isset($filters[$key])) { $sql.=' AND '.$column.'=:'.$key; $params[$key]=$filters[$key]; }
            }
            $batch=$this->db->execute($sql.' ORDER BY e.id LIMIT 100',$params)->fetchAll();
            foreach ($batch as $link) {
                $after=(int)$link['id'];
                if (isset($filters['codigos']) && !in_array($link['codigo_expediente'],$filters['codigos'],true)) { continue; }
                $available=true;
                try { $attributes=$cadastre->find($link['tipo_expediente'],$link['codigo_expediente'])['atributos']; }
                catch (\Sgpm\Http\HttpException $error) { if ($error->status()!==404) { throw $error; } $available=false; $attributes=array_fill_keys($schema::attributes(),null); $attributes['CODIGO_EXPEDIENTE']=$link['codigo_expediente']; }
                $matches=true;
                foreach (array('titular'=>'SOLICITANTES_O_TITULARES','municipio'=>'MUNICIPIOS','departamento'=>'DEPARTAMENTOS','mineral'=>'MINERALES') as $key=>$field) {
                    if (isset($filters[$key]) && ($attributes[$field]===null || mb_stripos((string)$attributes[$field],$filters[$key],0,'UTF-8')===false)) { $matches=false; break; }
                }
                if (!$matches) { continue; }
                yield array_merge($attributes,array('proyecto_id'=>(int)$link['proyecto_id'],'proyecto_nombre'=>$link['proyecto_nombre'],'tipo_expediente'=>$link['tipo_expediente'],'filiacion'=>$link['filiacion'],'empresa_vecina_id'=>$link['empresa_vecina_id']===null?null:(int)$link['empresa_vecina_id'],'fuente_disponible'=>$available));
            }
        } while (count($batch)===100);
    }
}
