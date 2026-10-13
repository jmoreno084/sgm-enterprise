<?php
declare(strict_types=1);
namespace Sgpm\Modules\Usuarios;

class UserRepository
{
    private $db;
    const FIELDS = 'id,correo,numero_documento,nombre,ultimo_acceso,estado,es_administrador,version';
    public function __construct(\Sgpm\Infrastructure\Database $db) { $this->db = $db; }
    public static function normalizeEmail(string $email): string { return mb_strtolower(trim($email), 'UTF-8'); }
    public static function dto(array $row): array
    {
        $result = array_intersect_key($row, array_flip(explode(',', self::FIELDS)));
        foreach (array('id', 'version') as $key) { if (isset($result[$key])) { $result[$key] = (int)$result[$key]; } }
        if (isset($result['es_administrador'])) { $result['es_administrador'] = in_array($result['es_administrador'], array(true, 1, '1', 't'), true); }
        return $result;
    }
    public function find(int $id): array
    {
        $row = $this->db->execute('SELECT '.self::FIELDS.' FROM sgpm.usuario WHERE id=:id', array('id'=>$id))->fetch();
        if (!$row) { throw new \Sgpm\Http\HttpException(404,'NO_ENCONTRADO','El usuario no esta disponible.'); }
        return self::dto($row);
    }
    public function credentials(string $email): array
    {
        $row = $this->db->execute('SELECT * FROM sgpm.usuario WHERE correo=:correo', array('correo'=>self::normalizeEmail($email)))->fetch();
        return $row ?: array();
    }
    public function listing(array $filters): array
    {
        $page = max(1, (int)(isset($filters['page']) ? $filters['page'] : 1));
        $size = max(1, min(100, (int)(isset($filters['page_size']) ? $filters['page_size'] : 25)));
        $where = array('TRUE'); $params = array();
        foreach (array('correo','nombre','estado') as $field) {
            if (isset($filters[$field]) && is_string($filters[$field]) && $filters[$field] !== '') {
                $where[] = $field . ($field === 'estado' ? '=:' : ' ILIKE :') . $field;
                $params[$field] = $field === 'estado' ? $filters[$field] : '%'.$filters[$field].'%';
            }
        }
        $condition = implode(' AND ', $where);
        $total = (int)$this->db->execute('SELECT count(*) FROM sgpm.usuario WHERE '.$condition, $params)->fetchColumn();
        $params['limit']=$size; $params['offset']=($page-1)*$size;
        $rows=$this->db->execute('SELECT '.self::FIELDS.' FROM sgpm.usuario WHERE '.$condition.' ORDER BY id LIMIT :limit OFFSET :offset',$params)->fetchAll();
        return array('items'=>array_map(array(__CLASS__,'dto'), $rows),'page'=>$page,'page_size'=>$size,'total'=>$total);
    }
    public function insert(array $data, string $hash): array
    {
        $params=array_intersect_key($data,array_flip(array('correo','nombre','numero_documento','estado','es_administrador')));
        $params['hash']=$hash;
        $id=(int)$this->db->execute('INSERT INTO sgpm.usuario(correo,nombre,numero_documento,estado,es_administrador,password_hash) VALUES(:correo,:nombre,:numero_documento,:estado,:es_administrador,:hash) RETURNING id',$params)->fetchColumn();
        return $this->find($id);
    }
}
