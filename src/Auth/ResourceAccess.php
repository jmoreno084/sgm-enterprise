<?php
declare(strict_types=1);
namespace Sgpm\Auth;

class ResourceAccess
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function actor(array $context): array
    {
        $user=$this->db->execute('SELECT id,estado,es_administrador FROM sgpm.usuario WHERE id=:id',array('id'=>isset($context['usuario_id'])?$context['usuario_id']:0))->fetch();
        if (!$user || $user['estado']!=='ACTIVO') { throw new \Sgpm\Http\HttpException(401,'NO_AUTENTICADO','Inicia sesión para continuar.'); }
        return $user;
    }
    public function admin(array $context)
    {
        if (!Authorization::permits($this->actor($context),'admin')) { throw new \Sgpm\Http\HttpException(403,'SIN_PERMISO','Solo un Administrador puede realizar esta operación.'); }
    }
    public function company(int $id,string $capability,array $context): array
    {
        $company=(new \Sgpm\Http\AuthorizationMiddleware($this->db))->requireCompany($this->actor($context),$id,$capability);
        if ($capability==='editar' && $company['estado']!=='ACTIVO') { throw new \Sgpm\Http\HttpException(409,'EMPRESA_INACTIVA','Reactiva la empresa antes de editar sus proyectos.'); }
        return $company;
    }
    public function project(int $id,string $capability,array $context): array
    {
        $project=$this->db->execute('SELECT * FROM sgpm.proyecto WHERE id=:id',array('id'=>$id))->fetch();
        if (!$project) { self::missing(); }
        $this->company((int)$project['empresa_id'],$capability,$context);
        if ($capability==='editar' && $project['estado']!=='ACTIVO') { throw new \Sgpm\Http\HttpException(409,'PROYECTO_INACTIVO','Reactiva el proyecto antes de editar sus vínculos.'); }
        return $project;
    }
    public static function missing() { throw new \Sgpm\Http\HttpException(404,'NO_ENCONTRADO','El recurso solicitado no está disponible.'); }
}
