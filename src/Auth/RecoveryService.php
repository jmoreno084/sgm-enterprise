<?php
declare(strict_types=1);
namespace Sgpm\Auth;

class RecoveryService
{
    private $db;
    private $outbox;
    private $config;
    public function __construct(\Sgpm\Infrastructure\Database $db,\Sgpm\Jobs\NotificationRepository $outbox,\Sgpm\Infrastructure\Configuration $config)
    { $this->db=$db; $this->outbox=$outbox; $this->config=$config; }
    public function request(string $email,array $context)
    {
        (new RateLimiter($this->db))->consume('recovery',$context['ip'],$email);
        $token=bin2hex(random_bytes(32));
        $db=$this->db;
        $db->pdo()->beginTransaction();
        try {
            $user=(new \Sgpm\Modules\Usuarios\UserRepository($db))->credentials($email);
            if ($user && $user['estado']==='ACTIVO') {
                $row=$db->execute('INSERT INTO sgpm.recuperacion_claves(usuario_id,token_hash) VALUES(:usuario,:hash) RETURNING id,expira_en',array('usuario'=>(int)$user['id'],'hash'=>hash('sha256',$token)))->fetch();
                (new RecoveryNotifications($this->outbox,$this->config->get('base_url')))->enqueue((int)$user['id'],(int)$row['id'],$token,$row['expira_en']);
            }
            $db->pdo()->commit();
        } catch (\Throwable $error) { $db->pdo()->rollBack(); throw $error; }
    }
    public function confirm(string $token,string $password,string $confirmation,array $context)
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token) || !hash_equals($password,$confirmation)) { $this->invalid(); }
        $hash=(new PasswordPolicy())->hash($password);
        $candidate=$this->db->execute('SELECT usuario_id FROM sgpm.recuperacion_claves WHERE token_hash=:token',array('token'=>hash('sha256',$token)))->fetch();
        if (!$candidate) { $this->invalid(); }
        $context['usuario_id']=(int)$candidate['usuario_id'];
        unset($context['sistema']);
        (new \Sgpm\Infrastructure\Transaction($this->db))->run($context,function ($db) use ($token,$hash) {
            // Consistent user-before-token lock order serializes all recoveries for one account.
            $candidate=$db->execute('SELECT usuario_id FROM sgpm.recuperacion_claves WHERE token_hash=:token',array('token'=>hash('sha256',$token)))->fetch();
            if (!$candidate) { $this->invalid(); }
            $user=$db->execute('SELECT id,estado FROM sgpm.usuario WHERE id=:id FOR UPDATE',array('id'=>(int)$candidate['usuario_id']))->fetch();
            $recovery=$db->execute("SELECT id FROM sgpm.recuperacion_claves WHERE token_hash=:token AND consumido_en IS NULL AND expira_en>timezone('UTC',now()) FOR UPDATE",array('token'=>hash('sha256',$token)))->fetch();
            if (!$user || $user['estado']!=='ACTIVO' || !$recovery) { $this->invalid(); }
            $db->execute('UPDATE sgpm.usuario SET password_hash=:hash,auth_version=auth_version+1,version=version+1 WHERE id=:id',array('hash'=>$hash,'id'=>(int)$user['id']));
            $db->execute("UPDATE sgpm.recuperacion_claves SET consumido_en=timezone('UTC',now()) WHERE usuario_id=:id AND consumido_en IS NULL",array('id'=>(int)$user['id']));
            $db->execute("UPDATE sgpm.notificaciones_outbox SET estado='FALLIDO',payload_cifrado='',procesado_en=timezone('UTC',now()),error_codigo='TOKEN_CONSUMIDO' WHERE usuario_destino_id=:id AND tipo='RECUPERACION' AND estado='PENDIENTE'",array('id'=>(int)$user['id']));
        });
    }
    private function invalid() { throw new \Sgpm\Http\HttpException(400,'TOKEN_INVALIDO','El enlace no es valido o ya expiro.'); }
}
