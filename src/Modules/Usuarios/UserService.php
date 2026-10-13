<?php
declare(strict_types=1);
namespace Sgpm\Modules\Usuarios;

class UserService
{
    private $db;
    private $outbox;
    public function __construct(\Sgpm\Infrastructure\Database $db,\Sgpm\Jobs\NotificationRepository $outbox)
    { $this->db=$db; $this->outbox=$outbox; }
    public function requireAdmin(array $context)
    {
        $user=$this->db->execute('SELECT estado,es_administrador FROM sgpm.usuario WHERE id=:id',array('id'=>isset($context['usuario_id'])?$context['usuario_id']:0))->fetch();
        if (!$user || !\Sgpm\Auth\Authorization::permits($user,'admin')) { throw new \Sgpm\Http\HttpException(403,'SIN_PERMISO','Solo un Administrador puede realizar esta operacion.'); }
    }
    public function create(array $data,array $context): array
    {
        $this->requireAdmin($context);
        $fields=$this->validate($data);
        $hash=(new \Sgpm\Auth\PasswordPolicy())->hash(isset($data['password']) && is_string($data['password'])?$data['password']:'');
        return $this->mutate($context,function ($db) use ($fields,$hash,$context) {
            $this->requireAdmin($context);
            $user=(new UserRepository($db))->insert($fields,$hash);
            (new UserNotifications($this->outbox))->welcome($user['id']);
            return $user;
        });
    }
    public function update(int $id,array $data,array $context): array
    {
        $this->requireAdmin($context);
        if (!isset($data['version']) || !is_int($data['version']) || $data['version']<1) { throw new \Sgpm\Http\HttpException(400,'VERSION_REQUERIDA','Indica la version del usuario.'); }
        return $this->mutate($context,function ($db) use ($id,$data,$context) {
            $db->execute('SELECT pg_advisory_xact_lock(534750,2)');
            $this->requireAdmin($context);
            $old=$db->execute('SELECT * FROM sgpm.usuario WHERE id=:id FOR UPDATE',array('id'=>$id))->fetch();
            if (!$old) { throw new \Sgpm\Http\HttpException(404,'NO_ENCONTRADO','El usuario no esta disponible.'); }
            if ((int)$old['version']!==$data['version']) { throw new \Sgpm\Http\HttpException(409,'VERSION_OBSOLETA','El usuario fue modificado. Actualiza la pagina.'); }
            $merged=array_merge(UserRepository::dto($old),$data);
            $fields=$this->validate($merged);
            $this->protectLastAdmin($old,$fields);
            $hash=$old['password_hash'];
            if (array_key_exists('password',$data)) {
                if (!is_string($data['password'])) { throw new \Sgpm\Http\HttpException(400,'CLAVE_INVALIDA','La clave no es valida.'); }
                $hash=(new \Sgpm\Auth\PasswordPolicy())->hash($data['password']);
            }
            $admin=UserRepository::dto($old)['es_administrador'];
            $fields['revoke']=array_key_exists('password',$data) || $fields['estado']!==$old['estado'] || $fields['es_administrador']!==$admin ? 1 : 0;
            $fields['hash']=$hash; $fields['id']=$id;
            $db->execute('UPDATE sgpm.usuario SET correo=:correo,nombre=:nombre,numero_documento=:numero_documento,estado=:estado,es_administrador=:es_administrador,password_hash=:hash,auth_version=auth_version+:revoke,version=version+1 WHERE id=:id',$fields);
            if ($fields['revoke']) { $db->execute("UPDATE sgpm.recuperacion_claves SET consumido_en=timezone('UTC',now()) WHERE usuario_id=:id AND consumido_en IS NULL",array('id'=>$id)); }
            return (new UserRepository($db))->find($id);
        });
    }
    public function delete(int $id,int $version,array $context)
    {
        $this->requireAdmin($context);
        $this->mutate($context,function ($db) use ($id,$version,$context) {
            $db->execute('SELECT pg_advisory_xact_lock(534750,2)');
            $this->requireAdmin($context);
            $old=$db->execute('SELECT * FROM sgpm.usuario WHERE id=:id FOR UPDATE',array('id'=>$id))->fetch();
            if (!$old) { throw new \Sgpm\Http\HttpException(404,'NO_ENCONTRADO','El usuario no esta disponible.'); }
            if ((int)$old['version']!==$version) { throw new \Sgpm\Http\HttpException(409,'VERSION_OBSOLETA','El usuario fue modificado.'); }
            $this->protectLastAdmin($old,array('estado'=>'INACTIVO','es_administrador'=>false));
            $db->execute('DELETE FROM sgpm.usuario WHERE id=:id',array('id'=>$id));
        });
    }
    private function protectLastAdmin(array $old,array $next)
    {
        if (\Sgpm\Auth\Authorization::permits($old,'admin') && ($next['estado']!=='ACTIVO' || !$next['es_administrador'])
            && (int)$this->db->execute("SELECT count(*) FROM sgpm.usuario WHERE estado='ACTIVO' AND es_administrador")->fetchColumn()<=1) {
            throw new \Sgpm\Http\HttpException(409,'ULTIMO_ADMINISTRADOR','Debe permanecer al menos un Administrador activo.');
        }
    }
    private function validate(array $data): array
    {
        $result=array();
        foreach (array('correo'=>255,'numero_documento'=>50,'nombre'=>255) as $field=>$limit) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field])==='' || mb_strlen($data[$field],'UTF-8')>$limit) { throw new \Sgpm\Http\HttpException(400,'CAMPOS_INVALIDOS','Revisa los campos obligatorios del usuario.'); }
            $result[$field]=trim($data[$field]);
        }
        $result['correo']=UserRepository::normalizeEmail($result['correo']);
        if (!filter_var($result['correo'],FILTER_VALIDATE_EMAIL)) { throw new \Sgpm\Http\HttpException(400,'CORREO_INVALIDO','El correo no es valido.'); }
        $result['estado']=isset($data['estado'])?$data['estado']:'ACTIVO';
        $result['es_administrador']=isset($data['es_administrador'])?$data['es_administrador']:false;
        if (!in_array($result['estado'],array('ACTIVO','INACTIVO'),true) || !is_bool($result['es_administrador'])) { throw new \Sgpm\Http\HttpException(400,'CAMPOS_INVALIDOS','Revisa el estado y el rol global.'); }
        return $result;
    }
    private function mutate(array $context,callable $callback)
    {
        try { return (new \Sgpm\Infrastructure\Transaction($this->db))->run($context,$callback); }
        catch (\PDOException $error) {
            if ($error->getCode()==='23505') { throw new \Sgpm\Http\HttpException(409,'CORREO_DUPLICADO','El correo ya esta registrado.'); }
            if ($error->getCode()==='23503') { throw new \Sgpm\Http\HttpException(409,'USUARIO_CON_HISTORIAL','El usuario tiene historial. Desactiva la cuenta para conservarlo.'); }
            throw $error;
        }
    }
}
