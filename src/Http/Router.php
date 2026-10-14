<?php
declare(strict_types=1);

namespace Sgpm\Http;

class Router
{
    private $routes = array();

    public function add(string $method, string $path, callable $handler,array $constraints=array())
    {
        $pattern = preg_quote($path, '~');
        $pattern = preg_replace_callback('/\\\\\{([a-zA-Z_]+)\\\\\}/',function ($match) use ($constraints) {
            return '(?P<'.$match[1].'>'.(isset($constraints[$match[1]])?$constraints[$match[1]]:'[1-9][0-9]*').')';
        },$pattern);
        $this->routes[] = array(strtoupper($method), '~^' . $pattern . '$~D', $handler, $path);
    }

    public function definitions(): array
    {
        return array_map(function ($route) { return array('method'=>$route[0],'path'=>$route[3]); },$this->routes);
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

