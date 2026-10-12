<?php
declare(strict_types=1);

namespace Sgpm\Auth;

class Csrf
{
    public static function token(array &$session): string
    {
        if (!isset($session['csrf'])) { $session['csrf'] = bin2hex(random_bytes(32)); }
        return $session['csrf'];
    }

    public static function verify(array $session, string $provided)
    {
        if (!isset($session['csrf']) || !hash_equals($session['csrf'], $provided)) {
            throw new \Sgpm\Http\HttpException(403, 'CSRF_INVALIDO', 'La sesión del formulario expiró. Actualiza la página.');
        }
    }
}

