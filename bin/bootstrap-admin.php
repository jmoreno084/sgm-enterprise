<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
$hidden = false;
try {
    if (PHP_VERSION !== '7.0.33' || DIRECTORY_SEPARATOR === '\\') {
        throw new RuntimeException('Usa el CLI PHP7.0.33 en Ubuntu con terminal protegida.');
    }
    $tty = fopen('/dev/tty', 'r+');
    if (!$tty) { throw new RuntimeException('Se requiere terminal interactiva.'); }
    $input = function ($label) use ($tty) {
        fwrite($tty, $label . ': ');
        $line = fgets($tty);
        if ($line === false) { throw new RuntimeException('Entrada incompleta.'); }
        return rtrim($line, "\r\n");
    };
    $email = strtolower(trim($input('Correo')));
    $name = trim($input('Nombre'));
    $document = trim($input('Documento'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255 || $name === ''
        || mb_strlen($name, 'UTF-8') > 255 || $document === '' || strlen($document) > 50) {
        throw new RuntimeException('Datos inválidos.');
    }
    system('stty -echo < /dev/tty', $status);
    if ($status !== 0) { throw new RuntimeException('No se pudo proteger la entrada.'); }
    $hidden = true;
    $password = $input('Clave');
    fwrite($tty, "\n");
    $confirm = $input('Confirmar clave');
    fwrite($tty, "\n");
    system('stty echo < /dev/tty');
    $hidden = false;
    if ($password !== $confirm || mb_strlen($password, 'UTF-8') < 12 || strlen($password) > 72) {
        throw new RuntimeException('Clave inválida.');
    }
    $config = new Sgpm\Infrastructure\Configuration(require dirname(__DIR__) . '/config/config.php');
    if ($config->get('db_user') !== 'sgpm_user') { throw new RuntimeException('Usa el rol runtime sgpm_user.'); }
    $database = new Sgpm\Infrastructure\Database($config);
    $transaction = new Sgpm\Infrastructure\Transaction($database);
    $transaction->run(array('usuario_id' => null, 'sistema' => true, 'ip' => '127.0.0.1',
        'request_id' => bin2hex(random_bytes(16))), function ($db) use ($email, $name, $document, $password) {
        $db->execute('LOCK TABLE sgpm.usuario IN SHARE ROW EXCLUSIVE MODE');
        if ((int) $db->execute("SELECT count(*) FROM sgpm.usuario WHERE es_administrador AND estado='ACTIVO'")->fetchColumn() !== 0) {
            throw new RuntimeException('Ya existe un Administrador activo.');
        }
        $db->execute('INSERT INTO sgpm.usuario(correo,password_hash,numero_documento,nombre,es_administrador) VALUES(:correo,:hash,:documento,:nombre,true)',
            array('correo' => $email, 'hash' => password_hash($password, PASSWORD_BCRYPT, array('cost' => 10)),
                'documento' => $document, 'nombre' => $name));
    });
    echo "Administrador inicial creado y auditado.\n";
} catch (Throwable $error) {
    if ($hidden) { system('stty echo < /dev/tty'); }
    fwrite(STDERR, "No se pudo crear el Administrador. Verifica los datos, configuración y terminal.\n");
    exit(1);
}

