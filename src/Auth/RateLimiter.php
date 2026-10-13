<?php
declare(strict_types=1);
namespace Sgpm\Auth;

class RateLimiter
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function consume(string $operation,string $ip,string $email)
    {
        if (!in_array($operation,array('login','recovery'),true) || filter_var($ip,FILTER_VALIDATE_IP)===false) {
            throw new \InvalidArgumentException('Limite invalido.');
        }
        $limit=$operation==='login'?10:3;
        $keys=array(hash('sha256',$operation.':ip:'.$ip),hash('sha256',$operation.':email:'.\Sgpm\Modules\Usuarios\UserRepository::normalizeEmail($email)));
        sort($keys,SORT_STRING);
        $blocked=false;
        $this->db->pdo()->beginTransaction();
        try {
            foreach ($keys as $key) {
                $row=$this->db->execute("INSERT INTO sgpm.limites_acceso(clave_hash,inicio_ventana,intentos) VALUES(:key,timezone('UTC',now()),1) ON CONFLICT (clave_hash) DO UPDATE SET inicio_ventana=CASE WHEN limites_acceso.inicio_ventana <= timezone('UTC',now())-interval '15 minutes' THEN timezone('UTC',now()) ELSE limites_acceso.inicio_ventana END, intentos=CASE WHEN limites_acceso.inicio_ventana <= timezone('UTC',now())-interval '15 minutes' THEN 1 ELSE limites_acceso.intentos+1 END RETURNING intentos",array('key'=>$key))->fetch();
                if ((int)$row['intentos']>$limit) {
                    $blocked=true;
                    $this->db->execute("UPDATE sgpm.limites_acceso SET bloqueado_hasta=inicio_ventana+interval '15 minutes' WHERE clave_hash=:key",array('key'=>$key));
                }
            }
            $this->db->pdo()->commit();
        } catch (\Throwable $error) { $this->db->pdo()->rollBack(); throw $error; }
        if ($blocked) { throw new \Sgpm\Http\HttpException(429,'LIMITE_TEMPORAL','Demasiados intentos. Intenta nuevamente mas tarde.'); }
    }
}
