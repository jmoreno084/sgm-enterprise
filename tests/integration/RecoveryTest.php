<?php
declare(strict_types=1);

return function ($assert) {
    $config = new Sgpm\Infrastructure\Configuration(require dirname(__DIR__, 2) . '/config/config.php');
    $config->assertTestDatabase();
    $db = new Sgpm\Infrastructure\Database($config);
    $users = new Sgpm\Modules\Usuarios\UserRepository($db);
    $cipher = new Sgpm\Infrastructure\PayloadCipher($config);
    $outbox = new Sgpm\Jobs\NotificationRepository($db, $cipher);
    $recovery = new Sgpm\Auth\RecoveryService($db, $outbox, $config);
    $context = array('usuario_id'=>null,'sistema'=>true,'ip'=>'127.0.0.3','request_id'=>bin2hex(random_bytes(16)));
    $email = 'recovery-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $user = (new Sgpm\Infrastructure\Transaction($db))->run($context, function () use ($users, $email) {
        return $users->insert(array('correo'=>$email,'nombre'=>'Recuperacion','numero_documento'=>'TEST','estado'=>'ACTIVO','es_administrador'=>false), (new Sgpm\Auth\PasswordPolicy())->hash('clave inicial segura'));
    });
    $recovery->request($email, $context);
    $row = $db->execute('SELECT * FROM sgpm.notificaciones_outbox WHERE usuario_destino_id=:id', array('id'=>(int)$user['id']))->fetch();
    $payload = $cipher->decrypt($row['payload_cifrado']);
    $assert(isset($payload['token']), 'Token solo en payload cifrado.');
    $assert((int)$db->execute("SELECT count(*) FROM sgpm.recuperacion_claves WHERE usuario_id=:id AND expira_en=creado_en+interval '30 minutes'", array('id'=>(int)$user['id']))->fetchColumn() === 1, 'Caducidad 30 minutos.');
    $recovery->confirm($payload['token'], 'clave nueva suficientemente larga', 'clave nueva suficientemente larga', $context);
    $updated = $users->credentials($email);
    $assert(password_verify('clave nueva suficientemente larga', $updated['password_hash']), 'Clave cambiada.');
    $assert((int)$updated['auth_version'] === 2, 'Sesiones anteriores invalidadas.');
    try { $recovery->confirm($payload['token'], 'otra clave suficientemente larga', 'otra clave suficientemente larga', $context); throw new RuntimeException('Token reutilizado.'); }
    catch (Sgpm\Http\HttpException $error) { $assert($error->status() === 400, 'Token de un solo uso.'); }
    $recovery->request($email, $context);
    $recovery->request($email, $context);
    try { $recovery->request($email, $context); throw new RuntimeException('Limite ignorado.'); }
    catch (Sgpm\Http\HttpException $error) { $assert($error->status() === 429, 'Recuperacion limita a tres.'); }
    $snapshot = $db->execute("SELECT valores_nuevos::text FROM sgpm.auditoria WHERE entidad='usuario' AND entidad_id=:id ORDER BY id DESC LIMIT 1", array('id'=>(int)$user['id']))->fetchColumn();
    $assert(strpos($snapshot, 'password_hash') === false && strpos($snapshot, $payload['token']) === false, 'Auditoria sin secretos.');
    // A new token in an independent rate-limit window must also reject expiration.
    $expiryContext=$context; $expiryContext['ip']='127.0.0.4';
    $expiredEmail='expired-'.bin2hex(random_bytes(6)).'@example.invalid';
    $expiredUser=(new Sgpm\Infrastructure\Transaction($db))->run($context,function () use ($users,$expiredEmail) {
        return $users->insert(array('correo'=>$expiredEmail,'nombre'=>'Caducado','numero_documento'=>'EXP','estado'=>'ACTIVO','es_administrador'=>false),(new Sgpm\Auth\PasswordPolicy())->hash('clave inicial segura'));
    });
    $recovery->request($expiredEmail,$expiryContext);
    $expiredRow=$db->execute("SELECT payload_cifrado FROM sgpm.notificaciones_outbox WHERE usuario_destino_id=:id AND tipo='RECUPERACION'",array('id'=>$expiredUser['id']))->fetch();
    $expiredPayload=$cipher->decrypt($expiredRow['payload_cifrado']);
    $db->execute("UPDATE sgpm.recuperacion_claves SET creado_en=timezone('UTC',now())-interval '31 minutes',expira_en=timezone('UTC',now())-interval '1 minute' WHERE usuario_id=:id",array('id'=>$expiredUser['id']));
    try { $recovery->confirm($expiredPayload['token'],'otra clave suficientemente larga','otra clave suficientemente larga',$expiryContext); throw new RuntimeException('Token caducado aceptado.'); }
    catch (Sgpm\Http\HttpException $error) { $assert($error->status()===400,'Token caducado rechazado.'); }
    $assert((int)$users->credentials($expiredEmail)['auth_version']===1,'Token caducado no cambia la cuenta.');
};
