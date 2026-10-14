<?php
declare(strict_types=1);
namespace Sgpm\Modules\Reportes;
class CsvExporter
{
    public static function cell($value): string
    {
        $text=ExportTable::text($value);
        if (is_string($value) && preg_match('/^[=+\-@\t\r\n]/',$text)) { $text="'".$text; }
        return $text;
    }
    public function write(\Traversable $rows,string $path,array $job)
    {
        $handle=fopen($path,'xb'); if (!$handle) { throw new \RuntimeException('Archivo no disponible.'); }
        try {
            $columns=ExportTable::columns(); fputcsv($handle,$columns,',','"');
            foreach ($rows as $row) { $values=array(); foreach ($columns as $key) { $values[]=self::cell($row[$key]); } if (fputcsv($handle,$values,',','"')===false) { throw new \RuntimeException('Escritura incompleta.'); } }
        } finally { fclose($handle); }
    }
}
