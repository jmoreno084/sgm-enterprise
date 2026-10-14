<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class PdfExporter
{
    public function write(\Traversable $rows,string $path,array $job)
    {
        \Sgpm\Infrastructure\Vendor::pdf(); $pdf=new \tFPDF('P','mm','A4'); $pdf->SetAutoPageBreak(true,15);
        $pdf->AddFont('DejaVu','','DejaVuSans.ttf',true); $pdf->AddPage(); $pdf->SetFont('DejaVu','',10);
        $pdf->MultiCell(180,6,'SGPM — Reporte de expedientes');
        $pdf->MultiCell(180,5,'Empresa: '.(isset($job['empresa_nombre'])?$job['empresa_nombre']:$job['empresa_id']).' | Proyecto: '.($job['proyecto_id']?:'Todos'));
        $pdf->MultiCell(180,5,'Fecha UTC: '.gmdate('Y-m-d H:i:s').' | Filtros: '.$job['filtros']);
        $pdf->MultiCell(180,5,'Leyenda: PROPIO = proyecto; VECINO = competencia. Fuente ausente: sin atributos oficiales ni geometría.');
        $count=0;
        foreach ($rows as $row) {
            ++$count; $pdf->Ln(3); $pdf->MultiCell(180,6,$row['CODIGO_EXPEDIENTE'].' — '.$row['proyecto_nombre'].' — '.$row['filiacion']);
            foreach (ExportTable::columns() as $field) {
                if ($field==='the_geom') { continue; }
                $pdf->SetFont('DejaVu','',8); $pdf->Cell(58,5,$field,1,0); $pdf->MultiCell(122,5,ExportTable::text($row[$field]),1);
            }
        }
        if (!$count) { $pdf->MultiCell(180,6,'Sin registros para los filtros seleccionados.'); }
        $pdf->Output('F',$path);
    }
}
