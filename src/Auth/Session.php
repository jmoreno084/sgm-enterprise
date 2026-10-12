<?php
declare(strict_types=1);

namespace Sgpm\Auth;

class Session
{
    public function open()
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name('SGPMSESSID');
        // SameSite por encabezado manual, sin firma de PHP7.3.
        session_set_cookie_params(0, '/', '', true, true);
        session_start();
        $now = time();
        if (!isset($_SESSION['iniciado'])) { $_SESSION['iniciado'] = $now; }
        if ((isset($_SESSION['actividad']) && $now - $_SESSION['actividad'] >= 1800)
            || $now - $_SESSION['iniciado'] >= 28800) {
            $_SESSION = array('iniciado' => $now);
            session_regenerate_id(true);
        }
        $_SESSION['actividad'] = $now;
        Csrf::token($_SESSION);
        $this->cookie(session_id());
    }

    public function authenticate(int $userId, int $authVersion)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { $this->open(); }
        session_regenerate_id(true);
        $_SESSION = array('usuario_id' => $userId, 'auth_version' => $authVersion,
            'iniciado' => time(), 'actividad' => time());
        Csrf::token($_SESSION);
        $this->cookie(session_id());
    }

    public function snapshot(): array
    {
        $value = $_SESSION;
        session_write_close();
        return $value;
    }

    public function destroy()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { $this->open(); }
        $_SESSION = array();
        session_destroy();
        header('Set-Cookie: SGPMSESSID=; Path=/; Max-Age=0; Secure; HttpOnly; SameSite=Lax', false);
    }

    private function cookie(string $id)
    {
        header('Set-Cookie: SGPMSESSID=' . rawurlencode($id) . '; Path=/; Secure; HttpOnly; SameSite=Lax', false);
    }
}

