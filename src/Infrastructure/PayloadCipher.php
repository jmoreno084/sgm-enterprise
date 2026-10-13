<?php
declare(strict_types=1);
namespace Sgpm\Infrastructure;

class PayloadCipher
{
    private $enc;
    private $mac;
    public function __construct(Configuration $config)
    {
        $this->enc=base64_decode($config->get('encryption_key'),true);
        $this->mac=base64_decode($config->get('mac_key'),true);
    }
    public function encrypt(array $payload): string
    {
        $plain=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($plain===false) { throw new \UnexpectedValueException('Payload invalido.'); }
        $iv=random_bytes(16);
        $encrypted=openssl_encrypt($plain,'aes-256-cbc',$this->enc,OPENSSL_RAW_DATA,$iv);
        if ($encrypted===false) { throw new \RuntimeException('Cifrado no disponible.'); }
        $message=$iv.$encrypted;
        return base64_encode($message.hash_hmac('sha256',$message,$this->mac,true));
    }
    public function decrypt(string $payload): array
    {
        $bytes=base64_decode($payload,true);
        if ($bytes===false || strlen($bytes)<64) { throw new \UnexpectedValueException('Payload invalido.'); }
        $message=substr($bytes,0,-32);
        if (!hash_equals(hash_hmac('sha256',$message,$this->mac,true),substr($bytes,-32))) {
            throw new \UnexpectedValueException('Payload invalido.');
        }
        $plain=openssl_decrypt(substr($message,16),'aes-256-cbc',$this->enc,OPENSSL_RAW_DATA,substr($message,0,16));
        $value=$plain===false ? null : json_decode($plain,true);
        if (!is_array($value)) { throw new \UnexpectedValueException('Payload invalido.'); }
        return $value;
    }
}
