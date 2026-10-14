<?php
declare(strict_types=1);
namespace Sgpm\Modules\Visor;

class MapController
{
    private $config;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Configuration $config,string $requestId) { $this->config=$config; $this->requestId=$requestId; }
    public function handle(int $project,int $file=0): \Sgpm\Http\Response
    {
        $db=new \Sgpm\Infrastructure\Database($this->config); $context=(new \Sgpm\Http\Request($db,$this->requestId))->context();
        if ($file) { return \Sgpm\Http\Response::json((new IdentifyService($db))->find($project,$file,$context),$this->requestId); }
        $result=(new MapRepository($db))->features($project,$_GET,$context);
        return \Sgpm\Http\Response::json($result,$this->requestId,200,$result['meta']);
    }
}
