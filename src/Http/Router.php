<?php
declare(strict_types=1);

namespace Sgpm\Http;

class Router
{
    private $routes = array();

    public function add(string $method, string $path, callable $handler)
    {
        $pattern = preg_quote($path, '~');
        $pattern = preg_replace('/\\\\\{([a-zA-Z_]+)\\\\\}/', '(?P<$1>[1-9][0-9]*)', $pattern);
        $this->routes[] = array(strtoupper($method), '~^' . $pattern . '$~D', $handler);
    }

    public function dispatch(string $method, string $uri): Response
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path)) {
            throw new HttpException(400, 'RUTA_INVALIDA', 'La ruta solicitada es inválida.');
        }
        foreach ($this->routes as $route) {
            if ($route[0] === strtoupper($method) && preg_match($route[1], $path, $matches)) {
                $params = array_filter($matches, function ($key) { return is_string($key); }, ARRAY_FILTER_USE_KEY);
                $response = call_user_func($route[2], $params);
                if (!$response instanceof Response) { throw new \RuntimeException('Respuesta de ruta inválida.'); }
                return $response;
            }
        }
        throw new HttpException(404, 'NO_ENCONTRADO', 'El recurso solicitado no está disponible.');
    }
}

