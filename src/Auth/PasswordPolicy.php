<?php
declare(strict_types=1);
namespace Sgpm\Auth;

class PasswordPolicy
{
    public function hash(string $password): string
    {
        if (!mb_check_encoding($password, 'UTF-8') || mb_strlen($password, 'UTF-8') < 12 || strlen($password) > 72 || strpos($password, "\0") !== false) {
            throw new \Sgpm\Http\HttpException(400, 'CLAVE_INVALIDA', 'La clave debe tener al menos 12 caracteres y no superar 72 bytes.');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT, array('cost'=>10));
        if ($hash === false) { throw new \RuntimeException('No se pudo proteger la clave.'); }
        return $hash;
    }
}
