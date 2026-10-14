<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class XlsxExporter
{
    private function cell(\XMLWriter $x,$value,string $field)
    {
        $text=ExportTable::text($value); $x->startElement('c');
        if ($value!==null && in_array($field,array('AREA_HA','proyecto_id','empresa_vecina_id'),true) && is_numeric($value)) { $x->writeAttribute('t','n'); $x->writeElement('v',(string)$value); }
        elseif ($value!==null && strpos($field,'FECHA_')===0 && preg_match('/^\d{4}-\d{2}-\d{2}$/D',$text)) {
            $x->writeAttribute('s','1'); $x->writeAttribute('t','n'); $x->writeElement('v',(string)((strtotime($text.' UTC')-strtotime('1899-12-30 UTC'))/86400));
        } else { $x->writeAttribute('t','inlineStr'); $x->startElement('is'); $x->startElement('t'); $x->writeAttribute('xml:space','preserve'); $x->text($text); $x->endElement(); $x->endElement(); }
        $x->endElement();
    }
    public function write(\Traversable $rows,string $path,array $job)
    {
        $tmp=new \Sgpm\Infrastructure\TemporaryFiles(); $zip=null;
        try {
            $x=new \XMLWriter(); $x->openURI($tmp->path('sheet.xml')); $x->startDocument('1.0','UTF-8'); $x->startElementNS(null,'worksheet','http://schemas.openxmlformats.org/spreadsheetml/2006/main'); $x->startElement('sheetData');
            $long=new \XMLWriter(); $long->openURI($tmp->path('long.xml')); $long->startDocument('1.0','UTF-8'); $long->startElementNS(null,'worksheet','http://schemas.openxmlformats.org/spreadsheetml/2006/main'); $long->startElement('sheetData');
            $x->startElement('row'); foreach (ExportTable::columns() as $field) { $this->cell($x,$field,''); } $x->endElement(); $number=1; $longRows=0;
            foreach ($rows as $row) {
                if (++$number>1048576) { throw new \RuntimeException('Límite OOXML excedido.'); }
                $x->startElement('row');
                foreach (ExportTable::columns() as $field) {
                    $value=$row[$field]; $text=ExportTable::text($value);
                    if (mb_strlen($text,'UTF-8')>30000) {
                        $marker='TEXTOS_LARGOS fila '.$number.' campo '.$field;
                        for ($offset=0,$part=1;$offset<mb_strlen($text,'UTF-8');$offset+=30000,++$part) {
                            if (++$longRows>1048576) { throw new \RuntimeException('Límite OOXML excedido.'); }
                            $long->startElement('row'); foreach (array($number,$field,$part,mb_substr($text,$offset,30000,'UTF-8')) as $item) { $this->cell($long,$item,''); } $long->endElement();
                        } $value=$marker;
                    }
                    $this->cell($x,$value,$field);
                } $x->endElement();
            }
            foreach (array($x,$long) as $writer) { $writer->endElement(); $writer->endElement(); $writer->endDocument(); $writer->flush(); }
            $zip=new \ZipArchive(); if ($zip->open($path,\ZipArchive::CREATE|\ZipArchive::EXCL)!==true) { throw new \RuntimeException('Archivo no disponible.'); }
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Expedientes" sheetId="1" r:id="rId1"/><sheet name="TEXTOS_LARGOS" sheetId="2" r:id="rId2"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml','<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs></styleSheet>');
            $zip->addFile($tmp->path('sheet.xml'),'xl/worksheets/sheet1.xml'); $zip->addFile($tmp->path('long.xml'),'xl/worksheets/sheet2.xml');
            if (!$zip->close()) { throw new \RuntimeException('Escritura incompleta.'); } $zip=null;
        } finally { if ($zip!==null) { $zip->close(); } $tmp->close(); }
    }
}
