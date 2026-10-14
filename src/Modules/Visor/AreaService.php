<?php
declare(strict_types=1);
namespace Sgpm\Modules\Visor;

class AreaService
{
    public static function value($official,$geodesic): array
    {
        if ($official!==null) { return array('area_ha'=>(float)$official,'area_origen'=>'OFICIAL'); }
        if ($geodesic!==null) { return array('area_ha'=>(float)$geodesic,'area_origen'=>'GEODESICA'); }
        return array('area_ha'=>null,'area_origen'=>'NO_DISPONIBLE');
    }
}
