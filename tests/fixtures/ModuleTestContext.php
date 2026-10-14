<?php
declare(strict_types=1);

class SgpmModuleTestContext
{
    public $db;
    public $config;
    public $admin;
    public $geo;
    public $manager;
    public $a;
    public $b;
    public function __construct()
    {
        $this->config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php');
        $this->config->assertTestDatabase();
        $this->db=new Sgpm\Infrastructure\Database($this->config);
        foreach (array('admin'=>'admin','geo'=>'geologo','manager'=>'gerente') as $key=>$email) {
            $id=(int)$this->db->execute('SELECT id FROM sgpm.usuario WHERE correo=:email',array('email'=>$email.'@example.invalid'))->fetchColumn();
            $this->$key=array('usuario_id'=>$id,'ip'=>'127.10.0.1','request_id'=>bin2hex(random_bytes(16)));
        }
        $this->a=(int)$this->db->execute("SELECT id FROM sgpm.empresa WHERE nit='SGPM-TEST-A'")->fetchColumn();
        $this->b=(int)$this->db->execute("SELECT id FROM sgpm.empresa WHERE nit='SGPM-TEST-B'")->fetchColumn();
    }
    public function expect(int $status,callable $operation)
    {
        try { $operation(); }
        catch (Sgpm\Http\HttpException $error) {
            if ($error->status()===$status) { return; }
            throw new RuntimeException('HTTP esperado '.$status.', recibido '.$error->status());
        }
        throw new RuntimeException('Operacion debio rechazar con HTTP '.$status);
    }
    public function project(int $company=0): array
    {
        return (new Sgpm\Modules\Proyectos\ProjectService($this->db))->save($company?:$this->a,0,
            array('nombre'=>'Proyecto Ñ '.bin2hex(random_bytes(4)),'mineral_principal'=>'Oro','estado'=>'ACTIVO'),$this->admin);
    }
}
