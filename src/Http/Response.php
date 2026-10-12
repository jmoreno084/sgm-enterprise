<?php
declare(strict_types=1);

namespace Sgpm\Http;

class Response
{
    private $body;
    private $status;
    private $headers;

    public function __construct(string $body, int $status = 200, array $headers = array())
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function json(array $data, string $requestId, int $status = 200): Response
    {
        return self::encoded(array('data' => $data, 'meta' => array('request_id' => $requestId)), $status);
    }

    public static function error(string $code, string $message, string $requestId, int $status): Response
    {
        return self::encoded(array('error' => array('code' => $code, 'message' => $message),
            'meta' => array('request_id' => $requestId)), $status);
    }

    private static function encoded(array $value, int $status): Response
    {
        $body = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \RuntimeException('No se pudo codificar la respuesta.');
        }
        return new self($body, $status, array('Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'));
    }

    public function send()
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) { header($name . ': ' . $value); }
        if ($this->status !== 204) { echo $this->body; }
    }

    public function status(): int { return $this->status; }
    public function body(): string { return $this->body; }
}

