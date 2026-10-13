<?php
declare(strict_types=1);
namespace Sgpm\Infrastructure;

class LibCurlModel
{
    private $status=0;
    private $error=0;
    public function curl_download(string $url,array $params=array()): string
    {
        if ($url!=='https://sigmin.automatizzar.com/admin_emails/sendEmail.php') { throw new \InvalidArgumentException('URL de correo invalida.'); }
        return $this->transfer($url,$params);
    }
    // Protected seam for local TLS/HTTP fixtures; public API retains its fixed destination.
    protected function transfer(string $url,array $params,array $testOptions=array()): string
    {
        $this->status=0; $this->error=0;
        $handle=curl_init($url);
        if ($handle===false) { throw new \RuntimeException('Transporte no disponible.'); }
        $options=array(CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params,'','&'),
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,
            CURLOPT_USERAGENT=>'SGPM-System-Ubuntu16/1.0',CURLOPT_HTTPHEADER=>array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt_array($handle,array_replace($options,$testOptions));
        $body=curl_exec($handle);
        $this->status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);
        $this->error=curl_errno($handle);
        curl_close($handle);
        return is_string($body)?$body:'';
    }
    public function status(): int { return $this->status; }
    public function error(): int { return $this->error; }
}
