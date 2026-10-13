<?php
declare(strict_types=1);
namespace Sgpm\Modules\Usuarios;

class MembershipRepository
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function listing(int $company): array
    {
        if (!$this->db->execute('SELECT id FROM sgpm.empresa WHERE id=:id',array('id'=>$company))->fetch()) { throw new \Sgpm\Http\HttpException(404,'NO_ENCONTRADO','La empresa no esta disponible.'); }
        return $this->db->execute('SELECT m.id,m.empresa_id,m.usuario_id,m.rol,m.estado,m.version,u.nombre,u.correo FROM sgpm.empresa_usuarios m JOIN sgpm.usuario u ON u.id=m.usuario_id WHERE empresa_id=:id ORDER BY m.id',array('id'=>$company))->fetchAll();
    }
}
