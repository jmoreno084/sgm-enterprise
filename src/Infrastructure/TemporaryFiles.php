<?php
declare(strict_types=1);
namespace Sgpm\Infrastructure;

class TemporaryFiles
{
    private $directory;
    public function __construct()
    {
        $this->directory=sys_get_temp_dir().'/sgpm-work-'.bin2hex(random_bytes(16));
        if (!mkdir($this->directory,0700)) { throw new \RuntimeException('No se pudo preparar un temporal privado.'); }
    }
    public function directory(): string { return $this->directory; }
    public function path(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_.-]+$/D',$name) || $name==='.' || $name==='..') { throw new \InvalidArgumentException('Nombre temporal invalido.'); }
        return $this->directory.'/'.$name;
    }
    public function close()
    {
        if (!is_dir($this->directory)) { return; }
        foreach (glob($this->directory.'/*') as $file) { if (is_file($file) || is_link($file)) { unlink($file); } }
        rmdir($this->directory);
    }
}
