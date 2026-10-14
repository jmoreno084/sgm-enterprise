<?php
declare(strict_types=1);
namespace Sgpm\Http;
class FileResponse extends Response
{
    private $file;
    private $type;
    private $name;
    public function __construct(string $file,string $type,string $name)
    { parent::__construct(''); $this->file=$file; $this->type=$type; $this->name=$name; }
    public function send()
    {
        $handle=fopen($this->file,'rb'); if (!$handle) { throw new \RuntimeException('Archivo no disponible.'); }
        try {
            http_response_code(200); header('Content-Type: '.$this->type); header('Content-Disposition: attachment; filename="'.$this->name.'"');
            header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff'); header('Content-Length: '.filesize($this->file));
            fpassthru($handle);
        } finally { fclose($handle); }
    }
}
