<?php
declare(strict_types=1);

namespace Sgpm\Infrastructure;

class Configuration
{
    private $values;

    public function __construct(array $values)
    {
        $this->values = $values;
        if (!in_array($this->get('env'), array('production', 'test', 'development'), true)) {
            throw new \InvalidArgumentException('Entorno inválido.');
        }
        $dsn = $this->get('dsn');
        if (!preg_match('/^pgsql:(?:host=(?:127\.0\.0\.1|localhost|\/[^;]+);)?(?:port=5432;)?dbname=([a-zA-Z0-9_]+)$/D', $dsn)) {
            throw new \InvalidArgumentException('Se requiere un DSN PostgreSQL local.');
        }
        if ($this->get('db_user') === '') {
            throw new \InvalidArgumentException('Falta usuario de base de datos.');
        }
        $url = parse_url($this->get('base_url'));
        if (!$url || !isset($url['scheme'], $url['host']) || $url['scheme'] !== 'https'
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
            throw new \InvalidArgumentException('BASE_URL debe usar HTTPS sin credenciales.');
        }
        if (!in_array($this->get('email_mode'), array('double', 'real'), true)) {
            throw new \InvalidArgumentException('Modo de correo inválido.');
        }
        if ($this->get('email_url') !== 'https://sigmin.automatizzar.com/admin_emails/sendEmail.php') {
            throw new \InvalidArgumentException('Endpoint de correo inválido.');
        }
        $enc = base64_decode($this->get('encryption_key'), true);
        $mac = base64_decode($this->get('mac_key'), true);
        if ($enc === false || $mac === false || strlen($enc) !== 32 || strlen($mac) !== 32 || hash_equals($enc, $mac)) {
            throw new \InvalidArgumentException('Las claves de cifrado y MAC deben ser independientes, de 32 bytes en base64.');
        }
        if ($this->get('email_mode') === 'real'
            && ($this->get('email_userkey') === '' || $this->get('email_apikey') === '')) {
            throw new \InvalidArgumentException('Faltan credenciales corporativas.');
        }
    }

    public function get(string $key): string
    {
        return isset($this->values[$key]) ? (string) $this->values[$key] : '';
    }

    public function assertTestDatabase()
    {
        $name = $this->get('test_database');
        if ($this->get('env') !== 'test' || !preg_match('/^sgpm_[a-z0-9_]*(?:test|acceptance)$/D', $name)
            || !preg_match('/(?:^|;)dbname=' . preg_quote($name, '/') . '$/D', $this->get('dsn'))) {
            throw new \RuntimeException('Las fixtures requieren una base aislada de pruebas explícita.');
        }
    }
}

