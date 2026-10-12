<?php
declare(strict_types=1);

namespace Sgpm\Infrastructure;

class Database
{
    private $pdo;

    public function __construct(Configuration $configuration)
    {
        $this->pdo = new \PDO($configuration->get('dsn'), $configuration->get('db_user'),
            $configuration->get('db_password'), array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC));
        $this->execute("SET client_encoding TO 'UTF8'");
        $this->execute("SET TIME ZONE 'UTC'");
    }

    public function pdo(): \PDO { return $this->pdo; }

    public function execute(string $sql, array $parameters = array()): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Parámetro SQL inválido.');
            }
            $type = $value === null ? \PDO::PARAM_NULL : (is_bool($value) ? \PDO::PARAM_BOOL
                : (is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
            $statement->bindValue(is_int($key) ? $key + 1 : ':' . ltrim($key, ':'), $value, $type);
        }
        $statement->execute();
        return $statement;
    }
}

