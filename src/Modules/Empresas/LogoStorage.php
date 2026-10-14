<?php
declare(strict_types=1);
namespace Sgpm\Modules\Empresas;

class LogoStorage
{
    private $directory;
    public function __construct(string $directory='') { $this->directory=$directory?:dirname(__DIR__,3).'/storage/logos'; }
    public function store(array $upload): string
    {
        if (!isset($upload['error'],$upload['tmp_name'],$upload['size']) || $upload['error']!==UPLOAD_ERR_OK) {
            $large=isset($upload['error']) && in_array($upload['error'],array(UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE),true);
            throw new \Sgpm\Http\HttpException($large?413:400,'ARCHIVO_INVALIDO','No fue posible recibir el archivo.');
        }
        if ($upload['size']>5*1024*1024 || filesize($upload['tmp_name'])>5*1024*1024) { throw new \Sgpm\Http\HttpException(413,'ARCHIVO_GRANDE','El logo admite hasta 5 MiB.'); }
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions=array('image/png'=>'png','image/jpeg'=>'jpg');
        $size=@getimagesize($upload['tmp_name']);
        if (!isset($extensions[$mime]) || !$size || $size['mime']!==$mime) { throw new \Sgpm\Http\HttpException(415,'FORMATO_INVALIDO','Selecciona una imagen PNG o JPEG válida.'); }
        if (!is_dir($this->directory) && !mkdir($this->directory,0700,true)) { throw new \RuntimeException('Almacenamiento privado no disponible.'); }
        $name=bin2hex(random_bytes(16)).'.'.$extensions[$mime];
        if (!move_uploaded_file($upload['tmp_name'],$this->directory.'/'.$name)) { throw new \RuntimeException('No se pudo guardar el logo.'); }
        chmod($this->directory.'/'.$name,0600); return $name;
    }
    public function path(string $name): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(png|jpg)$/D',$name) || !is_file($this->directory.'/'.$name)) { \Sgpm\Auth\ResourceAccess::missing(); }
        return $this->directory.'/'.$name;
    }
    public function discard(string $name)
    {
        if (preg_match('/^[a-f0-9]{32}\.(png|jpg)$/D',$name) && is_file($this->directory.'/'.$name)) { unlink($this->directory.'/'.$name); }
    }
}
