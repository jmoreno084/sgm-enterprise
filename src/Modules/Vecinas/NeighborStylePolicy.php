<?php
declare(strict_types=1);
namespace Sgpm\Modules\Vecinas;

class NeighborStylePolicy
{
    public static function assign(int $ordinal,array $used): array
    {
        $initial=array('#8a2be2','#9370db','#ba55d3','#800080','#9400d3','#9932cc','#8b008b','#663399');
        if ($ordinal<1) { throw new \InvalidArgumentException('Ordinal invalido.'); }
        if ($ordinal<=8) { return array('color_hex'=>$initial[$ordinal-1],'patron_indice'=>0); }
        $occupied=array_fill_keys(array_map('strtolower',array_merge($initial,$used)),true);
        for ($index=$ordinal-9;$index<96*64*64;$index++) {
            $color=sprintf('#%02x%02x%02x',96+(int)floor($index/4096),32+(int)floor(($index%4096)/64),192+$index%64);
            if (!isset($occupied[$color])) { return array('color_hex'=>$color,'patron_indice'=>1+(int)floor(($ordinal-9)/8)); }
        }
        throw new \Sgpm\Http\HttpException(409,'PALETA_AGOTADA','No quedan colores únicos disponibles para este proyecto.');
    }
}
