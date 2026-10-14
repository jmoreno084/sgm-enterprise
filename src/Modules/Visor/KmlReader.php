<?php
declare(strict_types=1);
namespace Sgpm\Modules\Visor;

class KmlReader
{
    private $xpath;
    private $vertices=0;
    public function read(string $text): array
    {
        if (stripos($text,'<!DOCTYPE')!==false || stripos($text,'<!ENTITY')!==false) { GeoJsonReader::invalid('El KML no admite DTD ni entidades externas.'); }
        $previous=libxml_use_internal_errors(true); $document=new \DOMDocument();
        try {
            if (!$document->loadXML($text,LIBXML_NONET|LIBXML_NOBLANKS) || $document->doctype!==null || !$document->documentElement || $document->documentElement->localName!=='kml' || $document->documentElement->namespaceURI!=='http://www.opengis.net/kml/2.2') { GeoJsonReader::invalid(); }
            $this->xpath=new \DOMXPath($document); $this->xpath->registerNamespace('k','http://www.opengis.net/kml/2.2'); $this->vertices=0;
            $places=$this->xpath->query('//k:Placemark'); if ($places->length>5000) { GeoJsonReader::limit(); }
            $features=array();
            foreach ($places as $place) {
                $properties=array('nombre'=>$this->xpath->evaluate('string(k:name)',$place),'descripcion'=>$this->xpath->evaluate('string(k:description)',$place));
                foreach ($this->xpath->query('k:ExtendedData/k:Data',$place) as $attribute) { $properties[$attribute->getAttribute('name')]=$this->xpath->evaluate('string(k:value)',$attribute); }
                $nodes=$this->xpath->query('k:Point|k:LineString|k:Polygon|k:MultiGeometry',$place);
                if ($nodes->length!==1) { GeoJsonReader::invalid(); }
                $features[]=array('type'=>'Feature','geometry'=>$this->geometry($nodes->item(0),0),'properties'=>$properties);
            }
            return (new GeoJsonReader())->normalize(array('type'=>'FeatureCollection','features'=>$features));
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
    private function geometry(\DOMNode $node,int $depth): array
    {
        if ($depth>16) { GeoJsonReader::invalid(); }
        if ($node->localName==='MultiGeometry') {
            $children=array(); foreach ($this->xpath->query('k:Point|k:LineString|k:Polygon|k:MultiGeometry',$node) as $child) { $children[]=$this->geometry($child,$depth+1); }
            if (!$children) { GeoJsonReader::invalid(); }
            return array('type'=>'GeometryCollection','geometries'=>$children);
        }
        if ($node->localName==='Polygon') {
            $outer=$this->xpath->query('k:outerBoundaryIs/k:LinearRing/k:coordinates',$node);
            if ($outer->length!==1) { GeoJsonReader::invalid(); }
            $rings=array($this->coordinates($outer->item(0)->textContent));
            foreach ($this->xpath->query('k:innerBoundaryIs/k:LinearRing/k:coordinates',$node) as $inner) { $rings[]=$this->coordinates($inner->textContent); }
            return array('type'=>'Polygon','coordinates'=>$rings);
        }
        $list=$this->coordinates($this->xpath->evaluate('string(k:coordinates)',$node));
        if ($node->localName==='Point' && count($list)!==1) { GeoJsonReader::invalid(); }
        return array('type'=>$node->localName==='Point'?'Point':'LineString','coordinates'=>$node->localName==='Point'?$list[0]:$list);
    }
    private function coordinates(string $text): array
    {
        $parts=preg_split('/\s+/',trim($text)); $result=array();
        foreach ($parts as $part) {
            $numbers=explode(',',$part); if (count($numbers)<2 || count($numbers)>3) { GeoJsonReader::invalid(); }
            $coordinate=array(); foreach ($numbers as $number) { if (!is_numeric($number) || !is_finite((float)$number)) { GeoJsonReader::invalid(); } $coordinate[]=(float)$number; }
            $result[]=$coordinate; if (++$this->vertices>500000) { GeoJsonReader::limit(); }
        }
        return $result;
    }
}
