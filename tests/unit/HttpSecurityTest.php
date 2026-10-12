<?php
declare(strict_types=1);
return function ($assert) {
    $router = new Sgpm\Http\Router();
    $router->add('GET', '/proyectos/{id}', function ($params) {
        return Sgpm\Http\Response::json(array('id' => (int) $params['id']), str_repeat('a',32));
    });
    $response = $router->dispatch('GET', '/proyectos/12?empresa_id=9');
    $assert(json_decode($response->body(), true)['data']['id'] === 12, 'Captura de identificador');
    foreach (array('/proyectos/0','/proyectos/12/extra','/proyectos/abc','/proyectos/%31') as $path) {
        try { $router->dispatch('GET', $path); throw new RuntimeException('Ruta no rechazada'); }
        catch (Sgpm\Http\HttpException $error) { $assert($error->status() === 404, 'Ruta cerrada'); }
    }
    $session = array();
    $token = Sgpm\Auth\Csrf::token($session);
    $assert(strlen($token) === 64, 'Token aleatorio');
    Sgpm\Auth\Csrf::verify($session, $token);
    try { Sgpm\Auth\Csrf::verify($session, 'incorrecto'); throw new RuntimeException('CSRF no rechazado'); }
    catch (Sgpm\Http\HttpException $error) { $assert($error->status() === 403, 'CSRF inválido'); }
    try { Sgpm\Infrastructure\OptimisticLock::assertChanged(0); throw new RuntimeException('Versión no rechazada'); }
    catch (Sgpm\Http\HttpException $error) { $assert($error->status() === 409, 'Conflicto optimista'); }
};

