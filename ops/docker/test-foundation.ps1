param(
    [string]$PhpContainer = 'mi-app-php',
    [string]$DatabaseContainer = 'mi-postgis',
    [switch]$KeepAcceptance
)
$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$testContainer = 'sgpm-foundation-' + [Guid]::NewGuid().ToString('N').Substring(0, 12)
$configPath = Join-Path $repoRoot 'config/config.php'
$metadataPath = Join-Path $repoRoot 'storage/acceptance/local.json'
if (Test-Path -LiteralPath $metadataPath) { throw 'Hay un entorno local registrado. Ejecuta ops/docker/stop-acceptance.ps1 primero.' }
if (Test-Path -LiteralPath $configPath) {
    throw 'El runner requiere config/config.php ausente para no usar una configuracion existente.'
}
function Invoke-DockerChecked([string[]]$DockerArguments) {
    & docker @DockerArguments
    if ($LASTEXITCODE -ne 0) { throw "Docker fallo (exit $LASTEXITCODE)." }
}
function Invoke-TestSql([string]$Sql) {
    $Sql | & docker exec -i $testContainer psql -U postgres -d sgpm_acceptance -v ON_ERROR_STOP=1
    if ($LASTEXITCODE -ne 0) { throw 'Fallo SQL en base temporal de pruebas.' }
}
$phpImage = & docker inspect $PhpContainer --format '{{.Image}}'
if ($LASTEXITCODE -ne 0) { throw 'No se encontro la imagen PHP local.' }
$dbImage = & docker inspect $DatabaseContainer --format '{{.Image}}'
if ($LASTEXITCODE -ne 0) { throw 'No se encontro la imagen PostGIS local.' }
$environment = @{
    POSTGRES_PASSWORD = [Guid]::NewGuid().ToString('N')
    SGPM_ENV = 'test'
    SGPM_DSN = 'pgsql:host=127.0.0.1;port=5432;dbname=sgpm_acceptance'
    SGPM_TEST_DATABASE = 'sgpm_acceptance'
    SGPM_DB_USER = 'sgpm_user'
    SGPM_DB_PASSWORD = [Guid]::NewGuid().ToString('N')
    SGPM_MIGRATION_DB_USER = 'sgpm_migrator'
    SGPM_MIGRATION_DB_PASSWORD = [Guid]::NewGuid().ToString('N')
    SGPM_FIXTURE_DB_USER = 'sgpm_fixture'
    SGPM_FIXTURE_DB_PASSWORD = [Guid]::NewGuid().ToString('N')
    SGPM_BASE_URL = 'https://sgpm.example.invalid'
    SGPM_EMAIL_MODE = 'double'
    SGPM_FIXTURE_PASSWORD = [Guid]::NewGuid().ToString('N')
}
foreach ($key in @('SGPM_ENCRYPTION_KEY', 'SGPM_MAC_KEY')) {
    $bytes = New-Object byte[] 32
    $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $random.GetBytes($bytes) } finally { $random.Dispose() }
    $environment[$key] = [Convert]::ToBase64String($bytes)
}
$previousEnvironment = @{}
foreach ($key in $environment.Keys) {
    $previousEnvironment[$key] = [Environment]::GetEnvironmentVariable($key, 'Process')
    [Environment]::SetEnvironmentVariable($key, $environment[$key], 'Process')
}
$createdConfig = $false
$createdContainer = $false
$webContainer = $testContainer + '-web'
$createdWeb = $false
$keepReady = $false
function Invoke-TestPhp([string[]]$PhpArguments) {
    $dockerArguments = @('run', '--rm', '--pull=never', '--network', "container:$testContainer",
        '--mount', "type=bind,source=$repoRoot,target=/workspace,readonly",
        '--tmpfs', '/workspace/storage:rw,mode=1777', '--tmpfs', '/workspace/logs:rw,mode=1777',
        '--workdir', '/workspace', '--entrypoint', 'php')
    foreach ($key in $environment.Keys) {
        if ($key -like 'SGPM_*') { $dockerArguments += @('--env', $key) }
    }
    $dockerArguments += @($phpImage) + $PhpArguments
    Invoke-DockerChecked -DockerArguments $dockerArguments
}
try {
    Copy-Item -LiteralPath (Join-Path $repoRoot 'config/config.example.php') -Destination $configPath
    $createdConfig = $true
    $dbArguments = @('run', '-d', '--pull=never', '--name', $testContainer, '--label', 'sgpm.acceptance=true',
        '--env', 'POSTGRES_PASSWORD', '--env', 'POSTGRES_DB=sgpm_acceptance',
        '--tmpfs', '/var/lib/postgresql/data:rw', $dbImage)
    if ($KeepAcceptance) { $dbArguments = @('run', '-d', '--publish', '127.0.0.1:18080:18080', '--publish', '127.0.0.1:18443:18443') + $dbArguments[2..($dbArguments.Length-1)] }
    Invoke-DockerChecked -DockerArguments $dbArguments
    $createdContainer = $true
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        & docker exec $testContainer pg_isready -h 127.0.0.1 -U postgres -d sgpm_acceptance *> $null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (!$ready) { throw 'PostgreSQL temporal no esta disponible.' }
    Invoke-TestSql 'CREATE EXTENSION IF NOT EXISTS postgis;'
    Invoke-TestSql ([System.IO.File]::ReadAllText((Join-Path $repoRoot 'ops/database/fixture-roles.sql')))
    Invoke-TestSql @"
CREATE ROLE sgpm_fixture LOGIN PASSWORD '$($environment.SGPM_FIXTURE_DB_PASSWORD)';
GRANT sgpm_fixture_owner TO sgpm_fixture;
"@
    Invoke-TestPhp -PhpArguments @('bin/seed-fixtures.php', '--external')
    Invoke-TestSql ([System.IO.File]::ReadAllText((Join-Path $repoRoot 'ops/database/roles.sql')))
    Invoke-TestSql @"
ALTER ROLE sgpm_user PASSWORD '$($environment.SGPM_DB_PASSWORD)';
CREATE ROLE sgpm_migrator LOGIN PASSWORD '$($environment.SGPM_MIGRATION_DB_PASSWORD)';
GRANT sgpm_owner TO sgpm_migrator;
GRANT CREATE ON DATABASE sgpm_acceptance TO sgpm_owner;
"@
    Invoke-TestPhp -PhpArguments @('bin/migrate.php', '--env=test')
    Invoke-TestSql ([System.IO.File]::ReadAllText((Join-Path $repoRoot 'ops/database/grants-runtime.sql')))
    Invoke-TestPhp -PhpArguments @('bin/seed-fixtures.php', '--profile=sgpm-acceptance')
    Invoke-TestPhp -PhpArguments @('tests/run.php', '--integration', '--contract')
    Invoke-TestPhp -PhpArguments @('bin/check-compatibility.php')
    if ($KeepAcceptance) {
        Invoke-TestPhp -PhpArguments @('tests/fixtures/prepare-browser.php')
        $webArguments = @('run', '-d', '--pull=never', '--name', $webContainer, '--label', 'sgpm.acceptance=true',
            '--network', "container:$testContainer", '--mount', "type=bind,source=$repoRoot,target=/workspace,readonly",
            '--tmpfs', '/workspace/storage:rw,mode=1777', '--tmpfs', '/workspace/logs:rw,mode=1777',
            '--workdir', '/workspace', '--entrypoint', 'php')
        foreach ($key in @('SGPM_ENV','SGPM_DSN','SGPM_DB_USER','SGPM_DB_PASSWORD','SGPM_EMAIL_MODE','SGPM_ENCRYPTION_KEY','SGPM_MAC_KEY','SGPM_TEST_DATABASE')) { $webArguments += @('--env', $key) }
        $webArguments += @('--env', 'SGPM_BASE_URL=https://localhost:18443', $phpImage, 'tests/fixtures/serve-acceptance.php')
        Invoke-DockerChecked -DockerArguments $webArguments
        $createdWeb = $true
        $webReady = $false
        for ($attempt = 0; $attempt -lt 30; $attempt++) {
            & docker exec $webContainer test -f /tmp/sgpm-acceptance.json *> $null
            if ($LASTEXITCODE -eq 0) { $webReady = $true; break }
            Start-Sleep -Seconds 1
        }
        if (!$webReady) { throw 'El entorno HTTPS local no pudo iniciar.' }
        $serverMetadata = (& docker exec $webContainer cat /tmp/sgpm-acceptance.json) | ConvertFrom-Json
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo leer el estado del entorno HTTPS.' }
        $metadataDirectory = Split-Path $metadataPath
        New-Item -ItemType Directory -Path $metadataDirectory -Force | Out-Null
        Invoke-DockerChecked -DockerArguments @('cp', "${webContainer}:$($serverMetadata.ca)", (Join-Path $metadataDirectory 'server.crt'))
        $metadata = @{database_container=$testContainer;web_container=$webContainer;url='https://localhost:18443';
            config_sha256=(Get-FileHash -LiteralPath $configPath -Algorithm SHA256).Hash;
            fixture_email='admin@example.invalid';fixture_password=$environment.SGPM_FIXTURE_PASSWORD}
        [System.IO.File]::WriteAllText($metadataPath, ($metadata | ConvertTo-Json), (New-Object System.Text.UTF8Encoding($false)))
        $keepReady = $true
        Write-Output 'Revision local: https://localhost:18443/login. Credenciales de fixture en storage/acceptance/local.json (ignorado por Git).'
        Write-Output 'Certificado solo de prueba; no se instalo confianza global. Finalizar: ./ops/docker/stop-acceptance.ps1'
    }
} finally {
    if (!$keepReady) {
        if ($createdWeb) { & docker rm -f $webContainer | Out-Null }
        if ($createdContainer) { & docker rm -f $testContainer | Out-Null }
        if ($createdConfig) { Remove-Item -LiteralPath $configPath }
    }
    foreach ($key in $previousEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($key, $previousEnvironment[$key], 'Process')
    }
}
