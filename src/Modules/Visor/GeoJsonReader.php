<?php
declare(strict_types=1);
namespace Sgpm\Modules\Visor;

class GeoJsonReader
{
    private $vertices=0;
    public function read(string $text): array
    {
        $value=json_decode($text,true,32);
        if (!is_array($value) || json_last_error()!==JSON_ERROR_NONE) { self::invalid(); }
        return $this->normalize($value);
    }
    public function normalize(array $value): array
    {
        $this->vertices=0;
        if (isset($value['crs']) && $value['crs']!==null) {
            $name=isset($value['crs']['properties']['name'])?$value['crs']['properties']['name']:'';
            if (!in_array($name,array('EPSG:4326','urn:ogc:def:crs:OGC:1.3:CRS84','urn:ogc:def:crs:EPSG::4326'),true)) { self::invalid('Convierte la capa a WGS84 (EPSG:4326).'); }
        }
        $type=isset($value['type'])?$value['type']:'';
        if ($type==='FeatureCollection') { $features=isset($value['features'])?$value['features']:null; }
        elseif ($type==='Feature') { $features=array($value); }
        else { $features=array(array('type'=>'Feature','geometry'=>$value,'properties'=>array())); }
        if (!is_array($features) || count($features)>5000) { self::limit(); }
        $out=array();
        foreach ($features as $feature) {
            if (!is_array($feature) || !isset($feature['type']) || $feature['type']!=='Feature' || !isset($feature['geometry']) || !is_array($feature['geometry'])) { self::invalid(); }
            $geometry=$this->geometry($feature['geometry'],0);
            $properties=isset($feature['properties'])?$feature['properties']:array();
            if (!is_array($properties)) { self::invalid(); }
            $entry=array('type'=>'Feature','geometry'=>$geometry,'properties'=>(object)$properties);
            if (isset($feature['id'])) {
                if ((!is_string($feature['id']) && !is_int($feature['id'])) || strlen((string)$feature['id'])>255) { self::invalid(); }
                $entry['id']=$feature['id'];
            }
            $out[]=$entry;
        }
        return array('type'=>'FeatureCollection','features'=>$out);
    }
    private function geometry(array $geometry,int $depth): array
    {
        if ($depth>16 || !isset($geometry['type']) || !is_string($geometry['type'])) { self::invalid(); }
        $type=$geometry['type'];
        if ($type==='GeometryCollection') {
            if (!isset($geometry['geometries']) || !is_array($geometry['geometries'])) { self::invalid(); }
            $result=array(); foreach ($geometry['geometries'] as $child) { if (!is_array($child)) { self::invalid(); } $result[]=$this->geometry($child,$depth+1); }
            return array('type'=>$type,'geometries'=>$result);
        }
        $levels=array('Point'=>0,'MultiPoint'=>1,'LineString'=>1,'MultiLineString'=>2,'Polygon'=>2,'MultiPolygon'=>3);
        if (!isset($levels[$type]) || !isset($geometry['coordinates']) || !is_array($geometry['coordinates'])) { self::invalid(); }
        $this->coordinates($geometry['coordinates'],$levels[$type]);
        if ($type==='LineString' && count($geometry['coordinates'])<2) { self::invalid(); }
        if ($type==='MultiLineString') { foreach ($geometry['coordinates'] as $line) { if (count($line)<2) { self::invalid(); } } }
        $polygons=$type==='Polygon'?array($geometry['coordinates']):($type==='MultiPolygon'?$geometry['coordinates']:array());
        foreach ($polygons as $polygon) { foreach ($polygon as $ring) { if (count($ring)<4 || $ring[0]!==$ring[count($ring)-1]) { self::invalid(); } } }
        return array('type'=>$type,'coordinates'=>$geometry['coordinates']);
    }
    private function coordinates(array $coordinates,int $level)
    {
        if ($level===0) {
            if (count($coordinates)<2 || count($coordinates)>3 || array_keys($coordinates)!==range(0,count($coordinates)-1)) { self::invalid(); }
            foreach ($coordinates as $value) { if ((!is_float($value) && !is_int($value)) || !is_finite((float)$value)) { self::invalid(); } }
            if (abs($coordinates[0])>180 || abs($coordinates[1])>90) { self::invalid('Las coordenadas deben usar WGS84 (EPSG:4326).'); }
            if (++$this->vertices>500000) { self::limit(); }
        } else {
            if (count($coordinates)===0) { self::invalid(); }
            foreach ($coordinates as $child) { if (!is_array($child)) { self::invalid(); } $this->coordinates($child,$level-1); }
        }
    }
    public static function invalid(string $message='El archivo geográfico no tiene un formato válido.') { throw new \Sgpm\Http\HttpException(400,'CAPA_INVALIDA',$message); }
    public static function limit() { throw new \Sgpm\Http\HttpException(413,'CAPA_EXCESIVA','La capa supera 5000 objetos o 500000 vértices.'); }
}
