<?php
declare(strict_types=1);
namespace Sgpm\Modules\Usuarios;

class MembershipService
{
    private $db;
    private $users;
    public function __construct(\Sgpm\Infrastructure\Database $db,UserService $users) { $this->db=$db; $this->users=$users; }
    public function save(int $company,int $id,array $data,array $context): array
    {
        $this->users->requireAdmin($context);
        if (!isset($data['rol']) || !in_array($data['rol'],array('GERENTE','GEOLOGO','INVERSIONISTA'),true)
            || !isset($data['estado']) || !in_array($data['estado'],array('ACTIVO','INACTIVO'),true)) { throw new \Sgpm\Http\HttpException(400,'MEMBRESIA_INVALIDA','Revisa el rol y estado de la membresia.'); }
        try {
            return (new \Sgpm\Infrastructure\Transaction($this->db))->run($context,function ($db) use ($company,$id,$data,$context) {
                $this->users->requireAdmin($context);
                if (!$db->execute('SELECT id FROM sgpm.empresa WHERE id=:id',array('id'=>$company))->fetch()) { throw new \Sgpm\Http\HttpException(404,'NO_ENCONTRADO','La empresa no esta disponible.'); }
                $params=array('empresa'=>$company,'rol'=>$data['rol'],'estado'=>$data['estado']);
                if ($id===0) {
                    if (!isset($data['usuario_id']) || !is_int($data['usuario_id']) || $data['usuario_id']<1) { throw new \Sgpm\Http\HttpException(400,'USUARIO_INVALIDO','Indica un usuario valido.'); }
                    $params['usuario']=$data['usuario_id'];
                    return $db->execute('INSERT INTO sgpm.empresa_usuarios(empresa_id,usuario_id,rol,estado) VALUES(:empresa,:usuario,:rol,:estado) RETURNING *',$params)->fetch();
                }
                if (!isset($data['version']) || !is_int($data['version'])) { throw new \Sgpm\Http\HttpException(400,'VERSION_REQUERIDA','Indica la version del vinculo.'); }
                $params['id']=$id; $params['version']=$data['version'];
                $row=$db->execute('UPDATE sgpm.empresa_usuarios SET rol=:rol,estado=:estado,version=version+1 WHERE empresa_id=:empresa AND id=:id AND version=:version RETURNING *',$params)->fetch();
                if (!$row) { $this->missingOrConflict($company,$id); }
                return $row;
            });
        } catch (\PDOException $error) {
            if ($error->getCode()==='23505') { throw new \Sgpm\Http\HttpException(409,'MEMBRESIA_DUPLICADA','El usuario ya pertenece a la empresa.'); }
            if ($error->getCode()==='23503') { throw new \Sgpm\Http\HttpException(400,'USUARIO_INVALIDO','El usuario no esta disponible.'); }
            throw $error;
        }
    }
    public function delete(int $company,int $id,int $version,array $context)
    {
        $this->users->requireAdmin($context);
        (new \Sgpm\Infrastructure\Transaction($this->db))->run($context,function ($db) use ($company,$id,$version,$context) {
            $this->users->requireAdmin($context);
            $row=$db->execute('DELETE FROM sgpm.empresa_usuarios WHERE empresa_id=:empresa AND id=:id AND version=:version RETURNING id',array('empresa'=>$company,'id'=>$id,'version'=>$version))->fetch();
            if (!$row) { $this->missingOrConflict($company,$id); }
        });
    }
    private function missingOrConflict(int $company,int $id)
    {
        $exists=$this->db->execute('SELECT id FROM sgpm.empresa_usuarios WHERE empresa_id=:empresa AND id=:id',array('empresa'=>$company,'id'=>$id))->fetch();
        throw new \Sgpm\Http\HttpException($exists?409:404,$exists?'VERSION_OBSOLETA':'NO_ENCONTRADO',$exists?'El vinculo fue modificado. Actualiza la pagina.':'El vinculo no esta disponible.');
    }
}
