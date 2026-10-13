<?php
declare(strict_types=1);

return function ($assert) {
    $policy = new Sgpm\Auth\PasswordPolicy();
    foreach (array('short', str_repeat('a', 73), str_repeat('é', 37)) as $invalid) {
        try { $policy->hash($invalid); throw new RuntimeException('Clave invalida aceptada.'); }
        catch (Sgpm\Http\HttpException $error) { $assert($error->status() === 400, 'Clave invalida devuelve 400.'); }
    }
    $hash = $policy->hash('una clave larga y segura');
    $assert(password_verify('una clave larga y segura', $hash), 'Bcrypt conserva la clave.');
    $assert(password_get_info($hash)['options']['cost'] >= 10, 'Costo bcrypt requerido.');
    $assert(Sgpm\Modules\Usuarios\UserRepository::normalizeEmail(' ADMIN@EXAMPLE.INVALID ') === 'admin@example.invalid', 'Correo canonico.');
    $dto = Sgpm\Modules\Usuarios\UserRepository::dto(array('id'=>1,'nombre'=>'Ana','password_hash'=>'secret','auth_version'=>7,'token_hash'=>'secret'));
    $assert(!isset($dto['password_hash'], $dto['auth_version'], $dto['token_hash']), 'DTO excluye secretos.');
    $values = require dirname(__DIR__, 2) . '/config/config.php';
    $config = new Sgpm\Infrastructure\Configuration($values);
    $config->assertTestDatabase();
    $cipher = new Sgpm\Infrastructure\PayloadCipher($config);
    $sealed = $cipher->encrypt(array('asunto'=>'Prueba', 'mensaje'=>'privado'));
    $assert($cipher->decrypt($sealed)['mensaje'] === 'privado', 'Cifrado reversible con claves validas.');
    $decoded = base64_decode($sealed, true);
    $decoded[20] = chr(ord($decoded[20]) ^ 1);
    try { $cipher->decrypt(base64_encode($decoded)); throw new RuntimeException('MAC no verificado.'); }
    catch (UnexpectedValueException $error) { $assert(true, 'Payload alterado rechazado.'); }
};
