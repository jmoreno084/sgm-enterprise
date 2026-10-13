<?php
declare(strict_types=1);
namespace Sgpm\Modules\Usuarios;

class UserNotifications
{
    private $outbox;
    public function __construct(\Sgpm\Jobs\NotificationRepository $outbox) { $this->outbox=$outbox; }
    public function welcome(int $userId)
    {
        $this->outbox->enqueue('BIENVENIDA',$userId,array('asunto'=>'Bienvenido a SGPM','mensaje'=>'Tu cuenta SGPM ha sido creada. Ingresa con tu acceso autorizado.'),'welcome:'.$userId,gmdate('Y-m-d H:i:s',time()+86400));
    }
}
