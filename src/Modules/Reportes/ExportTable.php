<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class ExportTable
{
    public static function columns(): array { return array_merge(\Sgpm\Modules\Catastro\CadastreSchema::attributes(),array('proyecto_id','proyecto_nombre','tipo_expediente','filiacion','empresa_vecina_id','fuente_disponible')); }
    public static function text($value): string
    {
        if ($value===null) { return ''; }
        if (is_bool($value)) { return $value?'true':'false'; }
        if (is_array($value)) { return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
        return (string)$value;
    }
    public static function manifest(array $job,array $missing,int $count): array
    { return array('crs'=>'EPSG:4326','origen'=>'Catastro oficial: geometría original','empresa_id'=>(int)$job['empresa_id'],'proyecto_id'=>$job['proyecto_id'],'filtros'=>json_decode($job['filtros'],true),'generado_en'=>gmdate('c'),'cantidad'=>$count,'omisiones'=>$missing); }
}
