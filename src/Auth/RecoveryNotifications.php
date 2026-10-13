<?php
declare(strict_types=1);
namespace Sgpm\Auth;

class RecoveryNotifications
{
    private $outbox;
    private $baseUrl;
    public function __construct(\Sgpm\Jobs\NotificationRepository $outbox,string $baseUrl) { $this->outbox=$outbox; $this->baseUrl=$baseUrl; }
    public function enqueue(int $userId,int $recoveryId,string $token,string $expires)
    {
        $url=rtrim($this->baseUrl,'/').'/recuperar#token='.$token;
        $this->outbox->enqueue('RECUPERACION',$userId,array('asunto'=>'Recuperacion de acceso SGPM','mensaje'=>'Recupera tu acceso: '.htmlspecialchars($url,ENT_QUOTES,'UTF-8'),'token'=>$token),'recovery:'.$recoveryId,$expires);
    }
}
