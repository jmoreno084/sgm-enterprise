<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class KmlExporter
{
    private function coordinates(array $coordinates): string
    { $values=array(); foreach ($coordinates as $point) { $values[]=implode(',',$point); } return implode(' ',$values); }
    private function polygon(\XMLWriter $xml,array $rings)
    {
        $xml->startElement('Polygon');
        $hasZ=isset($rings[0][0][2]); $xml->writeElement('altitudeMode',$hasZ?'absolute':'clampToGround');
        foreach ($rings as $index=>$ring) {
            $xml->startElement($index===0?'outerBoundaryIs':'innerBoundaryIs'); $xml->startElement('LinearRing');
            $xml->writeElement('coordinates',$this->coordinates($ring)); $xml->endElement(); $xml->endElement();
        }
        $xml->endElement();
    }
    public function write(\Traversable $rows,string $path,array $job)
    {
        $xml=new \XMLWriter(); if (!$xml->openURI($path)) { throw new \RuntimeException('Archivo no disponible.'); }
        $xml->startDocument('1.0','UTF-8'); $xml->startElementNS(null,'kml','http://www.opengis.net/kml/2.2'); $xml->startElement('Document');
        $xml->writeElement('description','WGS84 original. Sin Z en la fuente: clampToGround; sin altitudes inventadas.'); $missing=array(); $count=0;
        foreach ($rows as $row) {
            $geometry=$row['the_geom'];
            if ($geometry===null) { $missing[]=array('codigo'=>$row['CODIGO_EXPEDIENTE'],'proyecto_id'=>$row['proyecto_id'],'tipo'=>$row['tipo_expediente']); continue; }
            $xml->startElement('Placemark'); $xml->writeElement('name',$row['CODIGO_EXPEDIENTE']); $xml->startElement('ExtendedData');
            foreach ($row as $key=>$value) { if ($key==='the_geom') { continue; } $xml->startElement('Data'); $xml->writeAttribute('name',$key); $xml->writeElement('value',ExportTable::text($value)); $xml->endElement(); } $xml->endElement();
            if ($geometry['type']==='Polygon') { $this->polygon($xml,$geometry['coordinates']); }
            elseif ($geometry['type']==='MultiPolygon') { $xml->startElement('MultiGeometry'); foreach ($geometry['coordinates'] as $polygon) { $this->polygon($xml,$polygon); } $xml->endElement(); }
            else { throw new \RuntimeException('Geometría oficial no exportable.'); }
            $xml->endElement(); ++$count;
        }
        $xml->startElement('ExtendedData'); $xml->startElement('Data'); $xml->writeAttribute('name','manifest'); $xml->writeElement('value',json_encode(ExportTable::manifest($job,$missing,$count),JSON_UNESCAPED_UNICODE)); $xml->endElement(); $xml->endElement();
        $xml->endElement(); $xml->endElement(); $xml->endDocument(); $xml->flush();
    }
}
