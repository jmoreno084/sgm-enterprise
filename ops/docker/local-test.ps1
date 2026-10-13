param(
    [ValidateSet('start', 'stop', 'status', 'check')][string]$Action = 'start',
    [string]$PhpSourceContainer = 'mi-app-php',
    [string]$DatabaseSourceContainer = 'mi-postgis'
)
$ErrorActionPreference = 'Stop'
$repoRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$stateDirectory = Join-Path $repoRoot 'storage/local-test'
$metadataPath = Join-Path $stateDirectory 'local.json'
$utf8 = New-Object Text.UTF8Encoding($false)
$workspaceHash = [BitConverter]::ToString([Security.Cryptography.SHA256]::Create().ComputeHash([Text.Encoding]::UTF8.GetBytes($repoRoot.ToLowerInvariant()))).Replace('-', '').ToLowerInvariant()
$prefix = 'sgpm-enterprise-test-' + $workspaceHash.Substring(0, 8)
function Invoke-DockerChecked([string[]]$DockerArguments) {
    & docker.exe @DockerArguments
    if ($LASTEXITCODE -ne 0) { throw 'Docker fallo; se conservan todos los datos y recursos para diagnostico.' }
}
function Exists([string]$Kind, [string]$Name) {
    $ErrorActionPreference = 'Continue'
    & docker.exe $Kind inspect $Name *> $null
    return $LASTEXITCODE -eq 0
}
function AssertOwned([string]$Kind, [string]$Name) {
    if ($Kind -eq 'container') { $labelJson = & docker.exe inspect --format '{{json .Config.Labels}}' $Name }
    else { $labelJson = & docker.exe $Kind inspect --format '{{json .Labels}}' $Name }
    if ($LASTEXITCODE -ne 0 -or ($labelJson | ConvertFrom-Json).'sgpm.workspace' -ne $workspaceHash) { throw "Recurso ajeno: $Name. No se modifica." }
}
function WriteEnv([string]$Path, $Values) {
    $lines = foreach ($key in ($Values.Keys | Sort-Object)) { "$key=$($Values[$key])" }
    [IO.File]::WriteAllText($Path, (($lines -join "`n") + "`n"), $utf8)
}
if (!(Test-Path -LiteralPath $metadataPath)) {
    if ($Action -ne 'start') { throw 'Todavia no hay ambiente persistente registrado.' }
    foreach ($name in @("$prefix-db", "$prefix-web")) {
        if (Exists 'container' $name) { throw 'Nombre ocupado sin metadatos locales; no se reutiliza ni elimina.' }
    }
    foreach ($name in @("$prefix-data", "$prefix-runtime", "$prefix-logs")) {
        if (Exists 'volume' $name) { throw 'Volumen existente sin metadatos locales; no se reutiliza ni elimina.' }
    }
    $phpImage = & docker.exe inspect $PhpSourceContainer --format '{{.Image}}'
    if ($LASTEXITCODE -ne 0) { throw 'Imagen PHP original no disponible.' }
    $databaseImage = & docker.exe inspect $DatabaseSourceContainer --format '{{.Image}}'
    if ($LASTEXITCODE -ne 0) { throw 'Imagen PostgreSQL original no disponible.' }
    $environment = @{
        SGPM_ENV='test'; SGPM_DSN='pgsql:host=127.0.0.1;port=5432;dbname=sgpm_local_test'
        SGPM_TEST_DATABASE='sgpm_local_test'; SGPM_DB_USER='sgpm_user'
        SGPM_DB_PASSWORD=[Guid]::NewGuid().ToString('N')
        SGPM_MIGRATION_DB_USER='sgpm_migrator'; SGPM_MIGRATION_DB_PASSWORD=[Guid]::NewGuid().ToString('N')
        SGPM_FIXTURE_DB_USER='sgpm_fixture'; SGPM_FIXTURE_DB_PASSWORD=[Guid]::NewGuid().ToString('N')
        SGPM_FIXTURE_PASSWORD=[Guid]::NewGuid().ToString('N')
        SGPM_BASE_URL='https://localhost:18443'; SGPM_EMAIL_MODE='double'
    }
    foreach ($key in @('SGPM_ENCRYPTION_KEY', 'SGPM_MAC_KEY')) {
        $bytes = New-Object byte[] 32
        $random = [Security.Cryptography.RandomNumberGenerator]::Create()
        try { $random.GetBytes($bytes) } finally { $random.Dispose() }
        $environment[$key] = [Convert]::ToBase64String($bytes)
    }
    New-Item -ItemType Directory -Path $stateDirectory -Force | Out-Null
    $metadata = @{
        workspace=$repoRoot; workspace_hash=$workspaceHash; database='sgpm_local_test'
        database_container="$prefix-db"; web_container="$prefix-web"
        data_volume="$prefix-data"; runtime_volume="$prefix-runtime"; logs_volume="$prefix-logs"
        php_image=$phpImage; database_image=$databaseImage; url='https://localhost:18443'
        postgres_password=[Guid]::NewGuid().ToString('N'); environment=$environment
    }
    [IO.File]::WriteAllText($metadataPath, ($metadata | ConvertTo-Json -Depth 5), $utf8)
}
$metadata = Get-Content -LiteralPath $metadataPath -Raw | ConvertFrom-Json
if ($metadata.workspace -ne $repoRoot -or $metadata.workspace_hash -ne $workspaceHash -or $metadata.database -ne 'sgpm_local_test') { throw 'Metadatos fuera del workspace/base esperados.' }
foreach ($entry in @(@('database_container','db'), @('web_container','web'), @('data_volume','data'), @('runtime_volume','runtime'), @('logs_volume','logs'))) {
    if ($metadata.($entry[0]) -ne "$prefix-$($entry[1])") { throw 'Nombre de recurso fuera del ambiente esperado.' }
}
$db = $metadata.database_container
$web = $metadata.web_container
foreach ($name in @($db, $web)) { if (Exists 'container' $name) { AssertOwned 'container' $name } }
foreach ($name in @($metadata.data_volume,$metadata.runtime_volume,$metadata.logs_volume)) { if (Exists 'volume' $name) { AssertOwned 'volume' $name } }
if ($Action -eq 'status') {
    foreach ($name in @($db, $web)) { if (Exists 'container' $name) { Invoke-DockerChecked -DockerArguments @('inspect','--format','{{.Name}} {{.State.Status}}', $name) } }
    Write-Output "URL: $($metadata.url)/login"
    exit
}
if ($Action -eq 'stop') {
    if (Exists 'container' $web) { Invoke-DockerChecked -DockerArguments @('stop', $web) }
    if (Exists 'container' $db) { Invoke-DockerChecked -DockerArguments @('stop', $db) }
    Write-Output 'Ambiente detenido. Base, sesiones, certificados y credenciales conservados.'
    exit
}
if ($Action -eq 'check') {
    $credentialsPath = Join-Path $stateDirectory 'credentials.json'
    [IO.File]::ReadAllText($credentialsPath) | & docker.exe exec -i $web php /workspace/ops/docker/check-local-test.php
    if ($LASTEXITCODE -ne 0) { throw 'Verificacion US1 fallo; no se eliminan datos.' }
    exit
}
$envValues = @{}
foreach ($property in $metadata.environment.PSObject.Properties) { $envValues[$property.Name] = [string]$property.Value }
if ($envValues.SGPM_ENV -ne 'test' -or $envValues.SGPM_EMAIL_MODE -ne 'double' -or $envValues.SGPM_DSN -ne 'pgsql:host=127.0.0.1;port=5432;dbname=sgpm_local_test') { throw 'Configuracion fuera del modo aislado permitido.' }
WriteEnv (Join-Path $stateDirectory 'bootstrap.env') $envValues
$runtimeValues = @{}
foreach ($key in @('SGPM_ENV','SGPM_DSN','SGPM_TEST_DATABASE','SGPM_DB_USER','SGPM_DB_PASSWORD','SGPM_BASE_URL','SGPM_EMAIL_MODE','SGPM_ENCRYPTION_KEY','SGPM_MAC_KEY')) { $runtimeValues[$key] = $envValues[$key] }
WriteEnv (Join-Path $stateDirectory 'runtime.env') $runtimeValues
WriteEnv (Join-Path $stateDirectory 'database.env') @{POSTGRES_PASSWORD=$metadata.postgres_password; POSTGRES_DB=$metadata.database}
$configDirectory = Join-Path $stateDirectory 'config'
New-Item -ItemType Directory -Path $configDirectory -Force | Out-Null
foreach ($example in (Get-ChildItem -LiteralPath (Join-Path $repoRoot 'config') -Filter '*.example.php')) {
    if (!(Test-Path -LiteralPath (Join-Path $configDirectory $example.Name))) { Copy-Item -LiteralPath $example.FullName -Destination $configDirectory }
}
$configPath = Join-Path $configDirectory 'config.php'
if (!(Test-Path -LiteralPath $configPath)) { Copy-Item -LiteralPath (Join-Path $repoRoot 'config/config.example.php') -Destination $configPath }
$credentialsPath = Join-Path $stateDirectory 'credentials.json'
if (!(Test-Path -LiteralPath $credentialsPath)) {
    $credentials = @{url=$metadata.url; password=$envValues.SGPM_FIXTURE_PASSWORD; admin_email='admin@example.invalid'; fixture_emails=@('admin@example.invalid','gerente@example.invalid','geologo@example.invalid','inversionista@example.invalid')}
    [IO.File]::WriteAllText($credentialsPath,($credentials | ConvertTo-Json),$utf8)
}
foreach ($name in @($metadata.data_volume,$metadata.runtime_volume,$metadata.logs_volume)) {
    if (!(Exists 'volume' $name)) { Invoke-DockerChecked -DockerArguments @('volume','create','--label',"sgpm.workspace=$workspaceHash",'--label','sgpm.local-test=true',$name) }
}
if (!(Exists 'container' $db)) {
    Invoke-DockerChecked -DockerArguments @('run','-d','--pull=never','--name',$db,'--label',"sgpm.workspace=$workspaceHash",'--label','sgpm.local-test=true',
        '--restart','unless-stopped','--env-file',(Join-Path $stateDirectory 'database.env'),
        '--mount',"type=volume,source=$($metadata.data_volume),target=/var/lib/postgresql/data",
        '--publish','127.0.0.1:18080:18080','--publish','127.0.0.1:18443:18443',$metadata.database_image)
} else { Invoke-DockerChecked -DockerArguments @('start',$db) }
$ready = $false
for ($attempt=0; $attempt -lt 30; $attempt++) {
    & docker.exe exec $db pg_isready -h 127.0.0.1 -U postgres -d $metadata.database *> $null
    if ($LASTEXITCODE -eq 0) { $ready=$true; break }
    Start-Sleep -Seconds 1
}
if (!$ready) { throw 'PostgreSQL persistente no responde.' }
function Sql([string]$Statement) {
    $Statement | & docker.exe exec -i $db psql -U postgres -d $metadata.database -v ON_ERROR_STOP=1
    if ($LASTEXITCODE -ne 0) { throw 'SQL de preparacion fallo; datos conservados.' }
}
function Scalar([string]$Statement) {
    $value = & docker.exe exec $db psql -U postgres -d $metadata.database -At -v ON_ERROR_STOP=1 -c $Statement
    if ($LASTEXITCODE -ne 0) { throw 'Consulta de preparacion fallo.' }
    return "$value".Trim()
}
function Invoke-LocalPhp([string[]]$PhpArguments) {
    Invoke-DockerChecked -DockerArguments (@('run','--rm','--pull=never','--network',"container:$db",'--env-file',(Join-Path $stateDirectory 'bootstrap.env'),
        '--mount',"type=bind,source=$repoRoot,target=/workspace,readonly",'--mount',"type=bind,source=$configDirectory,target=/workspace/config,readonly",
        '--workdir','/workspace','--entrypoint','php',$metadata.php_image) + $PhpArguments)
}
Sql 'CREATE EXTENSION IF NOT EXISTS postgis;'
Sql ([IO.File]::ReadAllText((Join-Path $repoRoot 'ops/database/fixture-roles.sql')))
$fixturePassword = $envValues.SGPM_FIXTURE_DB_PASSWORD
Sql "DO `$`$ BEGIN IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='sgpm_fixture') THEN CREATE ROLE sgpm_fixture LOGIN PASSWORD '$fixturePassword'; END IF; END; `$`$; GRANT sgpm_fixture_owner TO sgpm_fixture;"
$external = Scalar "SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relname IN ('geo_titulos_mineros','geo_solicitudes_mineras')"
if ($external -eq '0') { Invoke-LocalPhp -PhpArguments @('bin/seed-fixtures.php','--external') }
elseif ($external -ne '2') { throw 'Fuentes fixture incompletas: no se reemplazan datos.' }
Sql ([IO.File]::ReadAllText((Join-Path $repoRoot 'ops/database/roles.sql')))
$runtimePassword = $envValues.SGPM_DB_PASSWORD
$migrationPassword = $envValues.SGPM_MIGRATION_DB_PASSWORD
Sql "ALTER ROLE sgpm_user PASSWORD '$runtimePassword'; DO `$`$ BEGIN IF NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname='sgpm_migrator') THEN CREATE ROLE sgpm_migrator LOGIN PASSWORD '$migrationPassword'; END IF; END; `$`$; GRANT sgpm_owner TO sgpm_migrator; GRANT CREATE ON DATABASE sgpm_local_test TO sgpm_owner;"
Invoke-LocalPhp -PhpArguments @('bin/migrate.php','--env=test')
Sql ([IO.File]::ReadAllText((Join-Path $repoRoot 'ops/database/grants-runtime.sql')))
if ((Scalar 'SELECT count(*) FROM sgpm.usuario') -eq '0') { Invoke-LocalPhp -PhpArguments @('bin/seed-fixtures.php','--profile=sgpm-acceptance') }
else { Write-Output 'Usuarios existentes conservados; no se recargan fixtures ni se cambian sus claves.' }
$certificate = Join-Path $stateDirectory 'server.crt'
$privateKey = Join-Path $stateDirectory 'server.key'
if (!(Test-Path -LiteralPath $certificate) -and !(Test-Path -LiteralPath $privateKey)) {
    $opensslConfig = "[req]`ndistinguished_name=dn`nx509_extensions=server`nprompt=no`n[dn]`nCN=localhost`nO=SGPM local test`n[server]`nsubjectAltName=DNS:localhost,IP:127.0.0.1`nbasicConstraints=critical,CA:TRUE`nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign`nextendedKeyUsage=serverAuth`n"
    [IO.File]::WriteAllText((Join-Path $stateDirectory 'openssl.cnf'),$opensslConfig,$utf8)
    Invoke-DockerChecked -DockerArguments @('run','--rm','--pull=never','--mount',"type=bind,source=$stateDirectory,target=/state",'--entrypoint','openssl',$metadata.php_image,
        'req','-x509','-nodes','-newkey','rsa:2048','-sha256','-days','365','-config','/state/openssl.cnf','-keyout','/state/server.key','-out','/state/server.crt')
} elseif (!(Test-Path -LiteralPath $certificate) -or !(Test-Path -LiteralPath $privateKey)) { throw 'Certificado/clave incompletos; no se sobrescribe ninguno.' }
$apachePath = Join-Path $stateDirectory 'apache.conf'
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'local-test-apache.conf') -Destination $apachePath -Force
if (!(Exists 'container' $web)) {
    Invoke-DockerChecked -DockerArguments @('run','-d','--pull=never','--name',$web,'--label',"sgpm.workspace=$workspaceHash",'--label','sgpm.local-test=true',
        '--restart','unless-stopped','--network',"container:$db",'--env-file',(Join-Path $stateDirectory 'runtime.env'),
        '--mount',"type=bind,source=$repoRoot,target=/workspace,readonly",'--mount',"type=bind,source=$configDirectory,target=/workspace/config,readonly",
        '--mount',"type=bind,source=$apachePath,target=/etc/sgpm-local/apache.conf,readonly",
        '--mount',"type=bind,source=$certificate,target=/etc/sgpm-local/server.crt,readonly",'--mount',"type=bind,source=$privateKey,target=/etc/sgpm-local/server.key,readonly",
        '--mount',"type=volume,source=$($metadata.runtime_volume),target=/var/lib/sgpm",'--mount',"type=volume,source=$($metadata.logs_volume),target=/workspace/logs",
        '--workdir','/workspace','--entrypoint','sh',$metadata.php_image,'-c',
        'install -d -o www-data -g www-data -m 0700 /var/lib/sgpm/sessions; chown www-data:www-data /workspace/logs; exec /usr/sbin/apache2 -f /etc/sgpm-local/apache.conf -DFOREGROUND')
} else { Invoke-DockerChecked -DockerArguments @('start',$web) }
$ready = $false
for ($attempt=0; $attempt -lt 30; $attempt++) {
    & curl.exe --silent --fail --cacert $certificate https://localhost:18443/login --output NUL
    if ($LASTEXITCODE -eq 0) { $ready=$true; break }
    Start-Sleep -Seconds 1
}
if (!$ready) { throw 'HTTPS no responde con certificado verificado; revisar docker logs del contenedor exclusivo.' }
Write-Output 'Ambiente persistente listo: https://localhost:18443/login'
Write-Output 'Credenciales: storage/local-test/credentials.json (excluido de Git).'
Write-Output 'Certificado: storage/local-test/server.crt. Su confianza en el navegador corresponde al usuario.'
