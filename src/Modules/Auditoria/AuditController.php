<?php
declare(strict_types=1);
namespace Sgpm\Modules\Auditoria;

class AuditController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(int $company=0,int $id=0): \Sgpm\Http\Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $context=(new \Sgpm\Http\Request($db,$this->requestId))->context();
        $repository=new AuditRepository($db);
        return \Sgpm\Http\Response::json($id?$repository->find($company,$id,$context):$repository->listing($company,$_GET,$context),$this->requestId);
    }
}
