<?php
declare(strict_types=1);

// Used only inside the disposable Linux acceptance container; never installed in production.
class SgpmLocalApache
{
    private $directory;
    private $process;
    private $httpsPort;
    private $httpPort;
    public function directory(): string { return $this->directory; }
    public function ca(): string { return $this->directory.'/server.crt'; }
    public function url(): string { return 'https://localhost:'.$this->httpsPort; }
    public function httpUrl(): string { return 'http://localhost:'.$this->httpPort; }
    private static function port(): int
    {
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$message);
        if ($socket===false) { throw new RuntimeException('Puerto local de pruebas no disponible.'); }
        $name=stream_socket_get_name($socket,false); fclose($socket);
        return (int)substr(strrchr($name,':'),1);
    }
    public function start(bool $expose=false)
    {
        $config=new Sgpm\Infrastructure\Configuration(require dirname(__DIR__,2).'/config/config.php');
        $config->assertTestDatabase();
        $this->directory=sys_get_temp_dir().'/sgpm-tls-'.bin2hex(random_bytes(8));
        if (!mkdir($this->directory,0755)) { throw new RuntimeException('Directorio temporal no disponible.'); }
        $this->httpPort=self::port(); $this->httpsPort=self::port();
        while ($this->httpPort===$this->httpsPort) { $this->httpsPort=self::port(); }
        if ($expose) {
            $this->httpPort=getenv('SGPM_ACCEPTANCE_HTTP_PORT')?(int)getenv('SGPM_ACCEPTANCE_HTTP_PORT'):18080;
            $this->httpsPort=getenv('SGPM_ACCEPTANCE_HTTPS_PORT')?(int)getenv('SGPM_ACCEPTANCE_HTTPS_PORT'):18443;
            if ($this->httpPort<1024 || $this->httpsPort<1024 || $this->httpPort>65535 || $this->httpsPort>65535 || $this->httpPort===$this->httpsPort) { throw new RuntimeException('Puertos fixture inválidos.'); }
        }
        mkdir($this->directory.'/sessions',0700);
        if (!chown($this->directory.'/sessions','www-data')) { throw new RuntimeException('Cuenta Apache de pruebas no disponible.'); }
        $certificateConfig="[req]\ndistinguished_name=dn\nx509_extensions=server\nprompt=no\n[dn]\nCN=localhost\n[server]\nsubjectAltName=DNS:localhost\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign\nextendedKeyUsage=serverAuth\n";
        file_put_contents($this->directory.'/openssl.cnf',$certificateConfig);
        if ($expose && getenv('SGPM_ACCEPTANCE_CERT') && getenv('SGPM_ACCEPTANCE_KEY')) {
            $certificate=realpath(getenv('SGPM_ACCEPTANCE_CERT')); $key=realpath(getenv('SGPM_ACCEPTANCE_KEY'));
            if ($certificate!=='/tmp/sgpm-reused-server.crt' || $key!=='/tmp/sgpm-reused-server.key') { throw new RuntimeException('Rutas de certificado fixture inválidas.'); }
            chmod($key,0600);
            if (!openssl_x509_check_private_key(file_get_contents($certificate),file_get_contents($key))) { throw new RuntimeException('Par de certificado fixture inválido.'); }
            copy($certificate,$this->ca()); copy($key,$this->directory.'/server.key');
            unlink($certificate); unlink($key);
        } else {
            $command='openssl req -x509 -nodes -newkey rsa:2048 -sha256 -days 1 -config '.escapeshellarg($this->directory.'/openssl.cnf').' -keyout '.escapeshellarg($this->directory.'/server.key').' -out '.escapeshellarg($this->ca()).' 2>/dev/null';
            exec($command,$output,$status);
            if ($status!==0) { throw new RuntimeException('No se pudo generar el certificado local de prueba.'); }
        }
        chmod($this->directory.'/server.key',0600);
        $root=dirname(__DIR__,2);
        $dir=$this->directory; $https=$this->httpsPort; $http=$this->httpPort;
        $bind=$expose?'*':'127.0.0.1';
        $listen=$expose?'0.0.0.0':'127.0.0.1';
        $apache=<<<CONF
ServerRoot /etc/apache2
ServerName localhost
PidFile $dir/apache.pid
DefaultRuntimeDir $dir
IncludeOptional /etc/apache2/mods-enabled/*.load
LoadModule ssl_module /usr/lib/apache2/modules/mod_ssl.so
LoadModule socache_shmcb_module /usr/lib/apache2/modules/mod_socache_shmcb.so
Include /etc/apache2/mods-available/headers.load
Include /etc/apache2/mods-enabled/php7.0.conf
User www-data
Group www-data
TypesConfig /etc/mime.types
DirectoryIndex index.php
ErrorLog $dir/error.log
LogLevel warn
Listen $listen:$http
Listen $listen:$https
SSLSessionCache none
PassEnv SGPM_ENV SGPM_DSN SGPM_DB_USER SGPM_DB_PASSWORD SGPM_BASE_URL SGPM_EMAIL_MODE SGPM_ENCRYPTION_KEY SGPM_MAC_KEY SGPM_TEST_DATABASE
<Directory />
 Require all denied
</Directory>
<VirtualHost $bind:$http>
 Redirect permanent / https://localhost:$https/
</VirtualHost>
<VirtualHost $bind:$https>
 DocumentRoot $root/public
 SSLEngine on
 SSLCertificateFile $dir/server.crt
 SSLCertificateKeyFile $dir/server.key
 SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
 Header always set Strict-Transport-Security "max-age=31536000"
 Header always set X-Content-Type-Options "nosniff"
 Header always set X-Frame-Options "DENY"
 Header always set Content-Security-Policy "default-src 'self'; script-src 'self' https://maps.googleapis.com https://maps.gstatic.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https://*.tile.openstreetmap.org https://*.arcgisonline.com https://*.google.com https://*.googleapis.com https://*.gstatic.com; connect-src 'self' https://*.tile.openstreetmap.org https://*.arcgisonline.com https://*.googleapis.com https://*.gstatic.com; font-src 'self' data:; worker-src 'self' blob:; frame-ancestors 'none'; object-src 'none'; base-uri 'self'; form-action 'self'"
 Header always set Referrer-Policy "same-origin"
 <IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE application/json application/geo+json text/html text/css application/javascript text/javascript
 </IfModule>
 php_flag display_errors Off
 php_value upload_max_filesize 20M
 php_value post_max_size 21M
 php_value session.save_path $dir/sessions
 <Directory $root/public>
  Require all granted
  AllowOverride None
  Options -Indexes -MultiViews
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^ index.php [QSA,L]
 </Directory>
 Alias /__fixture_mail $root/tests/fixtures/email-http.php
 <Directory $root/tests/fixtures>
  Require ip 127.0.0.1
  AcceptPathInfo On
 </Directory>
 <FilesMatch "^\\.">
  Require all denied
 </FilesMatch>
</VirtualHost>
CONF;
        file_put_contents($dir.'/apache.conf',$apache);
        $environment=array('PATH'=>getenv('PATH')?:'/usr/bin:/bin');
        foreach (array('SGPM_ENV','SGPM_DSN','SGPM_DB_USER','SGPM_DB_PASSWORD','SGPM_BASE_URL','SGPM_EMAIL_MODE','SGPM_ENCRYPTION_KEY','SGPM_MAC_KEY','SGPM_TEST_DATABASE') as $key) { $environment[$key]=getenv($key)?:''; }
        // Apache's foreground shutdown signals its process group. Isolate it so
        // a CLI test invoked via docker exec survives closing the fixture server.
        $command='exec /usr/bin/setsid /usr/sbin/apache2 -f '.escapeshellarg($dir.'/apache.conf').' -DFOREGROUND';
        $this->process=proc_open($command,array(0=>array('pipe','r'),1=>array('file',$dir.'/stdout.log','a'),2=>array('file',$dir.'/stderr.log','a')),$pipes,$root,$environment);
        if (!is_resource($this->process)) { throw new RuntimeException('Apache local no disponible.'); }
        fclose($pipes[0]);
        for ($attempt=0;$attempt<50;$attempt++) {
            $socket=@stream_socket_client('tcp://127.0.0.1:'.$this->httpsPort,$errno,$message,0.1);
            if ($socket!==false) { fclose($socket); return; }
            $state=proc_get_status($this->process);
            if (!$state['running']) { throw new RuntimeException('Apache de pruebas no pudo iniciar: '.trim(file_get_contents($dir.'/stderr.log'))); }
            usleep(100000);
        }
        throw new RuntimeException('Apache HTTPS no respondio.');
    }
    public function close()
    {
        if (is_resource($this->process)) { proc_terminate($this->process); proc_close($this->process); $this->process=null; }
        if (!$this->directory || !is_dir($this->directory)) { return; }
        foreach (glob($this->directory.'/sessions/*') as $file) { if (is_file($file)) { unlink($file); } }
        if (is_dir($this->directory.'/sessions')) { rmdir($this->directory.'/sessions'); }
        foreach (glob($this->directory.'/*') as $file) { if (is_file($file)) { unlink($file); } }
        rmdir($this->directory);
    }
}
