<?php
declare(strict_types=1);
namespace Sgpm\Auth;

class AuthenticationService
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function login(string $email,string $password,array $context): array
    {
        (new RateLimiter($this->db))->consume('login',$context['ip'],$email);
        $user=(new \Sgpm\Modules\Usuarios\UserRepository($this->db))->credentials($email);
        $fallback='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid=password_verify($password,$user ? $user['password_hash'] : $fallback);
        if (!$valid || strlen($password)>72 || !$user || $user['estado']!=='ACTIVO') {
            throw new \Sgpm\Http\HttpException(401,'ACCESO_INVALIDO','Correo o clave incorrectos.');
        }
        $context['usuario_id']=(int)$user['id'];
        return (new \Sgpm\Infrastructure\Transaction($this->db))->run($context,function ($db) use ($user,$password) {
            $current=$db->execute('SELECT * FROM sgpm.usuario WHERE id=:id FOR UPDATE',array('id'=>(int)$user['id']))->fetch();
            if (!$current || $current['estado']!=='ACTIVO' || !password_verify($password,$current['password_hash'])) {
                throw new \Sgpm\Http\HttpException(401,'ACCESO_INVALIDO','Correo o clave incorrectos.');
            }
            $db->execute("UPDATE sgpm.usuario SET ultimo_acceso=timezone('UTC',now()) WHERE id=:id",array('id'=>(int)$current['id']));
            $db->execute("SELECT sgpm.audit_auth('LOGIN')");
            return array('usuario_id'=>(int)$current['id'],'auth_version'=>(int)$current['auth_version']);
        });
    }
    public function logout(array $session,array $context)
    {
        $user=(new \Sgpm\Http\AuthorizationMiddleware($this->db))->user($session);
        $context['usuario_id']=(int)$user['id'];
        (new \Sgpm\Infrastructure\Transaction($this->db))->run($context,function ($db) { $db->execute("SELECT sgpm.audit_auth('LOGOUT')"); });
    }
}
