<?php
declare(strict_types=1);
return function ($assert) {
    $values = array('env'=>'test','dsn'=>'pgsql:host=127.0.0.1;port=5432;dbname=sgpm_acceptance',
        'db_user'=>'sgpm_user','base_url'=>'https://sgpm.example.invalid','email_mode'=>'double',
        'email_url'=>'https://sigmin.automatizzar.com/admin_emails/sendEmail.php',
        'encryption_key'=>base64_encode(str_repeat('a',32)),'mac_key'=>base64_encode(str_repeat('b',32)),
        'test_database'=>'sgpm_acceptance');
    $config = new Sgpm\Infrastructure\Configuration($values);
    $config->assertTestDatabase();
    foreach (array('dsn'=>'pgsql:host=remote.example;dbname=sgpm_acceptance','base_url'=>'http://sgpm.example.invalid',
        'mac_key'=>$values['encryption_key'],'email_mode'=>'smtp') as $key=>$value) {
        $bad=$values; $bad[$key]=$value;
        try { new Sgpm\Infrastructure\Configuration($bad); throw new RuntimeException('Configuración no rechazada: '.$key); }
        catch (InvalidArgumentException $error) { $assert(true, 'Configuración inválida'); }
    }
    $values['test_database']='produccion';
    try { (new Sgpm\Infrastructure\Configuration($values))->assertTestDatabase(); throw new RuntimeException('Fixture no protegida'); }
    catch (RuntimeException $error) { $assert($error->getMessage() !== 'Fixture no protegida', 'Guardia de entorno'); }
};

