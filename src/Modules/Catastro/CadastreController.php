<?php
declare(strict_types=1);
namespace Sgpm\Modules\Catastro;

class CadastreController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(string $type='',string $code=''): \Sgpm\Http\Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); (new \Sgpm\Http\Request($db,$this->requestId))->user();
        $repository=new CadastreRepository($db);
        return \Sgpm\Http\Response::json($type===''?$repository->listing($_GET):$repository->find($type,$code),$this->requestId);
    }
}
