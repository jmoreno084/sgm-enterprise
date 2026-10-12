<?php
declare(strict_types=1);

namespace Sgpm\Infrastructure;

class OptimisticLock
{
    public static function version($value): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \Sgpm\Http\HttpException(400, 'VERSION_INVALIDA', 'Envía una versión válida del registro.');
        }
        return $value;
    }

    public static function assertChanged(int $count)
    {
        if ($count !== 1) {
            throw new \Sgpm\Http\HttpException(409, 'VERSION_OBSOLETA', 'El registro cambió. Actualiza la información antes de guardar.');
        }
    }
}

