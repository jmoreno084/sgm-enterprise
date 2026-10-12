<?php
declare(strict_types=1);

// Copiar fuera de public; todas las credenciales proceden del entorno.
return array(
    'env' => getenv('SGPM_ENV') ?: 'production',
    'dsn' => getenv('SGPM_DSN') ?: '',
    'db_user' => getenv('SGPM_DB_USER') ?: '',
    'db_password' => getenv('SGPM_DB_PASSWORD') ?: '',
    'base_url' => getenv('SGPM_BASE_URL') ?: '',
    'email_mode' => getenv('SGPM_EMAIL_MODE') ?: 'double',
    'email_url' => 'https://sigmin.automatizzar.com/admin_emails/sendEmail.php',
    'email_userkey' => getenv('SGPM_EMAIL_USERKEY') ?: '',
    'email_apikey' => getenv('SGPM_EMAIL_APIKEY') ?: '',
    'encryption_key' => getenv('SGPM_ENCRYPTION_KEY') ?: '',
    'mac_key' => getenv('SGPM_MAC_KEY') ?: '',
    'test_database' => getenv('SGPM_TEST_DATABASE') ?: ''
);

