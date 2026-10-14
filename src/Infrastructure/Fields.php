<?php
declare(strict_types=1);
namespace Sgpm\Infrastructure;

class Fields
{
    public static function only(array $data,array $allowed)
    {
        foreach ($data as $key=>$value) {
            if (!in_array($key,$allowed,true)) { throw new \Sgpm\Http\HttpException(400,'CAMPO_NO_PERMITIDO','La solicitud contiene un campo no editable.'); }
        }
    }
    public static function text(array $data,string $key,int $length,bool $required=true)
    {
        if (!array_key_exists($key,$data) || $data[$key]===null || $data[$key]==='') {
            if (!$required) { return null; }
            self::invalid();
        }
        if (!is_string($data[$key]) || !mb_check_encoding($data[$key],'UTF-8')) { self::invalid(); }
        $value=trim($data[$key]);
        if (($required && $value==='') || mb_strlen($value,'UTF-8')>$length) { self::invalid(); }
        return $value===''?null:$value;
    }
    public static function state(array $data): string
    {
        $state=isset($data['estado'])?$data['estado']:'ACTIVO';
        if (!in_array($state,array('ACTIVO','INACTIVO'),true)) { self::invalid(); }
        return $state;
    }
    public static function date(array $data,string $key)
    {
        $value=self::text($data,$key,10,false);
        if ($value===null) { return null; }
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date || $date->format('Y-m-d')!==$value) { self::invalid(); }
        return $value;
    }
    public static function page(array $query): array
    {
        $page=isset($query['page'])?filter_var($query['page'],FILTER_VALIDATE_INT):1;
        $size=isset($query['page_size'])?filter_var($query['page_size'],FILTER_VALIDATE_INT):25;
        if ($page===false || $page<1 || $page>1000000 || $size===false || $size<1 || $size>100) { self::invalid(); }
        return array('page'=>$page,'page_size'=>$size,'offset'=>($page-1)*$size);
    }
    public static function row(array $row): array
    {
        foreach (array('id','empresa_id','proyecto_id','version','creado_por','modificado_por','empresa_vecina_id','usuario_id','patron_indice','orden_registro','vecina_secuencia') as $key) {
            if (isset($row[$key])) { $row[$key]=(int)$row[$key]; }
        }
        return $row;
    }
    private static function invalid() { throw new \Sgpm\Http\HttpException(400,'CAMPOS_INVALIDOS','Revisa los campos, fechas y valores indicados.'); }
}
