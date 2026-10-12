<?php
declare(strict_types=1);

namespace Sgpm\Infrastructure;

class Logger
{
    private $directory;
    public function __construct(string $directory) { $this->directory = $directory; }

    public function incident(string $requestId, string $category)
    {
        // Solo metadatos cerrados: nunca mensaje crudo, SQL, trace o payload.
        $record = json_encode(array('fecha' => gmdate('c'), 'request_id' => $requestId,
            'categoria' => preg_replace('/[^A-Za-z0-9_\\\\]/', '', $category))) . "\n";
        if (file_put_contents($this->directory . '/sgpm-' . gmdate('Y-m-d') . '.log', $record, FILE_APPEND | LOCK_EX) === false) {
            error_log('SGPM: no se pudo escribir el log de incidentes.');
        }
    }
}

