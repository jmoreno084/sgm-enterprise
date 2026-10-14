<?php
declare(strict_types=1);
namespace Sgpm\Modules\Vecinas;
use Sgpm\Infrastructure\Fields;

class ProjectNeighborRepository
{
    private $db;
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db=$db; }
    public function find(int $project,int $neighbor): array
    {
        $row=$this->db->execute('SELECT v.*,n.nombre,n.nit FROM sgpm.proyecto_empresas_vecinas v JOIN sgpm.empresa_vecina n ON n.id=v.empresa_vecina_id WHERE v.proyecto_id=:project AND v.empresa_vecina_id=:neighbor',array('project'=>$project,'neighbor'=>$neighbor))->fetch();
        if (!$row) { \Sgpm\Auth\ResourceAccess::missing(); }
        return Fields::row($row);
    }
    public function listing(int $project): array
    {
        return array_map(array(Fields::class,'row'),$this->db->execute('SELECT v.*,n.nombre,n.nit FROM sgpm.proyecto_empresas_vecinas v JOIN sgpm.empresa_vecina n ON n.id=v.empresa_vecina_id WHERE v.proyecto_id=:project ORDER BY v.orden_registro',array('project'=>$project))->fetchAll());
    }
    public function usedColors(int $project): array
    {
        return $this->db->execute("SELECT color_hex FROM sgpm.proyecto_empresas_vecinas WHERE proyecto_id=:current
            UNION SELECT valores_nuevos->>'color_hex' FROM sgpm.auditoria WHERE entidad='proyecto_empresas_vecinas' AND proyecto_id_contexto=:historic AND valores_nuevos->>'color_hex' IS NOT NULL",array('current'=>$project,'historic'=>$project))->fetchAll(\PDO::FETCH_COLUMN);
    }
}
