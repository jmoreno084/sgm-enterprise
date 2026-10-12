<?php
declare(strict_types=1);

namespace Sgpm\Infrastructure;

class Transaction
{
    private $database;
    public function __construct(Database $database) { $this->database = $database; }

    public function run(array $context, callable $work)
    {
        $actor = isset($context['usuario_id']) ? $context['usuario_id'] : null;
        if (($actor !== null && (!is_int($actor) || $actor < 1))
            || !isset($context['ip'], $context['request_id'])
            || filter_var($context['ip'], FILTER_VALIDATE_IP) === false
            || !preg_match('/^[a-f0-9]{32}$/D', $context['request_id'])
            || ($actor === null && (PHP_SAPI !== 'cli' || empty($context['sistema'])))) {
            throw new \InvalidArgumentException('Contexto de auditoría inválido.');
        }
        $pdo = $this->database->pdo();
        $pdo->beginTransaction();
        try {
            foreach (array('usuario_id' => $actor === null ? '' : (string) $actor,
                'ip' => $context['ip'], 'request_id' => $context['request_id'],
                'sistema' => $actor === null ? '1' : '0') as $name => $value) {
                $this->database->execute('SELECT set_config(:name, :value, true)',
                    array('name' => 'sgpm.' . $name, 'value' => $value));
            }
            $result = $work($this->database);
            $pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
    }
}

