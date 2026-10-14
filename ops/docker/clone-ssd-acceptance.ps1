$ErrorActionPreference='Stop'
$root=[System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$source=Get-Content -Raw (Join-Path $root 'storage/acceptance/local.json') | ConvertFrom-Json
if ($source.database_container -notmatch '^sgpm-foundation-[a-f0-9]{12}$' -or $source.web_container -ne ($source.database_container+'-web')) { throw 'Origen no autorizado' }
foreach ($name in @($source.database_container,$source.web_container)) {
 $labels=docker inspect $name --format '{{json .Config.Labels}}' | ConvertFrom-Json
 if ($LASTEXITCODE -ne 0 -or $labels.'sgpm.acceptance' -ne 'true') { throw 'Origen sin etiqueta de aceptación' }
}
$directory=Join-Path $root 'storage/acceptance/ssd'
if (Test-Path -LiteralPath (Join-Path $directory 'local.json')) { throw 'Ya existe clon SSD; no se reemplazan datos' }
New-Item -ItemType Directory -Path $directory -Force | Out-Null
$private=Join-Path $directory 'private-seed'
New-Item -ItemType Directory -Path $private | Out-Null
# Protect the temporary copy of the already trusted test key before copying it.
$acl=New-Object System.Security.AccessControl.DirectorySecurity
$identity=[System.Security.Principal.WindowsIdentity]::GetCurrent().User
$acl.SetOwner($identity); $acl.SetAccessRuleProtection($true,$false)
foreach ($sid in @($identity,(New-Object System.Security.Principal.SecurityIdentifier('S-1-5-18')))) {
 $rule=New-Object System.Security.AccessControl.FileSystemAccessRule($sid,'FullControl','ContainerInherit,ObjectInherit','None','Allow')
 $acl.AddAccessRule($rule)
}
Set-Acl -LiteralPath $private -AclObject $acl
$id=[Guid]::NewGuid().ToString('N').Substring(0,12)
$database='sgpm-foundation-'+$id; $web=$database+'-web'; $createdDb=$false; $createdWeb=$false; $ready=$false
$saved=@{}
function Checked-Docker([string[]]$Arguments) { & docker @Arguments; if ($LASTEXITCODE -ne 0) { throw 'Operación de clon desechable falló' } }
try {
 $dbImage=docker inspect $source.database_container --format '{{.Image}}'
 $phpImage=docker inspect $source.web_container --format '{{.Image}}'
 $dbEnvironment=docker inspect $source.database_container --format '{{json .Config.Env}}' | ConvertFrom-Json
 $webEnvironment=docker inspect $source.web_container --format '{{json .Config.Env}}' | ConvertFrom-Json
 foreach ($entry in @($dbEnvironment)+@($webEnvironment)) {
  $parts=$entry.Split('=',2)
  if ($parts[0] -eq 'POSTGRES_PASSWORD' -or $parts[0] -like 'SGPM_*') {
   if (!$saved.ContainsKey($parts[0])) { $saved[$parts[0]]=[Environment]::GetEnvironmentVariable($parts[0],'Process') }
   [Environment]::SetEnvironmentVariable($parts[0],$parts[1],'Process')
  }
 }
 $server=(docker exec $source.web_container cat /tmp/sgpm-acceptance.json) | ConvertFrom-Json
 if ($server.ca -notmatch '^/tmp/sgpm-tls-[a-f0-9]{16}/server.crt$') { throw 'Certificado de origen inesperado' }
 Checked-Docker -Arguments @('cp',($source.web_container+':'+$server.ca),(Join-Path $private 'server.crt'))
 Checked-Docker -Arguments @('cp',($source.web_container+':'+$server.ca.Replace('server.crt','server.key')),(Join-Path $private 'server.key'))
 Checked-Docker -Arguments @('exec',$source.database_container,'pg_dump','-U','postgres','-d','sgpm_acceptance','-Fc','-f','/tmp/sgpm-reference.dump')
 Checked-Docker -Arguments @('exec',$source.database_container,'pg_dumpall','-U','postgres','--roles-only','--file=/tmp/sgpm-reference-roles.sql')
 Checked-Docker -Arguments @('cp',($source.database_container+':/tmp/sgpm-reference.dump'),(Join-Path $private 'database.dump'))
 Checked-Docker -Arguments @('cp',($source.database_container+':/tmp/sgpm-reference-roles.sql'),(Join-Path $private 'roles.sql'))
 $roles=[System.IO.File]::ReadAllText((Join-Path $private 'roles.sql')).Replace('CREATE ROLE postgres;','')
 [System.IO.File]::WriteAllText((Join-Path $private 'roles.sql'),$roles,(New-Object System.Text.UTF8Encoding($false)))
 # PGDATA is in the disposable writable layer on Docker's C: NVMe VHD, not tmpfs.
 Checked-Docker -Arguments @('run','-d','--pull=never','--name',$database,'--label','sgpm.acceptance=true','--label','sgpm.storage=ssd','--cpus','2','--memory','6g','--memory-swap','6g',
  '--publish','127.0.0.1:28080:28080','--publish','127.0.0.1:28443:28443','--env','POSTGRES_PASSWORD','--env','POSTGRES_DB=postgres','--env','PGDATA=/var/lib/postgresql/sgpm-reference-data',$dbImage)
 $createdDb=$true; $dbReady=$false
 for ($i=0;$i -lt 30;$i++) { docker exec $database pg_isready -h 127.0.0.1 -U postgres -d postgres *> $null; if ($LASTEXITCODE -eq 0) { $dbReady=$true; break }; Start-Sleep -Seconds 1 }
 if (!$dbReady) { throw 'Clon PostgreSQL no disponible' }
 Checked-Docker -Arguments @('cp',(Join-Path $private 'roles.sql'),($database+':/tmp/sgpm-roles.sql'))
 Checked-Docker -Arguments @('cp',(Join-Path $private 'database.dump'),($database+':/tmp/sgpm-database.dump'))
 Checked-Docker -Arguments @('exec',$database,'psql','-U','postgres','-d','postgres','-v','ON_ERROR_STOP=1','-q','-f','/tmp/sgpm-roles.sql')
 Checked-Docker -Arguments @('exec',$database,'createdb','-U','postgres','--template=template0','sgpm_acceptance')
 Checked-Docker -Arguments @('exec',$database,'pg_restore','-U','postgres','-d','sgpm_acceptance','--exit-on-error','/tmp/sgpm-database.dump')
 $searchIndexes="CREATE EXTENSION IF NOT EXISTS pg_trgm; SET ROLE sgpm_fixture_owner;`n"+[System.IO.File]::ReadAllText((Join-Path $root 'ops/database/cadastre-search-indexes.sql'))
 $searchIndexes | docker exec -i $database psql -U postgres -d sgpm_acceptance -v ON_ERROR_STOP=1
 if ($LASTEXITCODE -ne 0) { throw 'Preparación de índices de texto falló' }
 $env:SGPM_BASE_URL='https://localhost:28443'
 $arguments=@('create','--pull=never','--name',$web,'--label','sgpm.acceptance=true','--label','sgpm.storage=ssd','--cpus','2','--memory','2g','--memory-swap','2g',
  '--network',('container:'+$database),'--mount',"type=bind,source=$root,target=/workspace,readonly",'--mount','type=volume,target=/workspace/storage,volume-nocopy','--mount','type=volume,target=/workspace/logs,volume-nocopy','--workdir','/workspace','--entrypoint','php')
 foreach ($key in @('SGPM_ENV','SGPM_DSN','SGPM_DB_USER','SGPM_DB_PASSWORD','SGPM_BASE_URL','SGPM_EMAIL_MODE','SGPM_ENCRYPTION_KEY','SGPM_MAC_KEY','SGPM_TEST_DATABASE')) { $arguments+=@('--env',$key) }
 $arguments+=@('--env','SGPM_ACCEPTANCE_HTTP_PORT=28080','--env','SGPM_ACCEPTANCE_HTTPS_PORT=28443','--env','SGPM_ACCEPTANCE_CERT=/tmp/sgpm-reused-server.crt','--env','SGPM_ACCEPTANCE_KEY=/tmp/sgpm-reused-server.key','--env','SGPM_STORAGE_FIXTURE_VOLUME=1',$phpImage,'tests/fixtures/serve-acceptance.php')
 Checked-Docker -Arguments $arguments; $createdWeb=$true
 Checked-Docker -Arguments @('cp',(Join-Path $private 'server.crt'),($web+':/tmp/sgpm-reused-server.crt'))
 Checked-Docker -Arguments @('cp',(Join-Path $private 'server.key'),($web+':/tmp/sgpm-reused-server.key'))
 Checked-Docker -Arguments @('start',$web)
 $webReady=$false
 for ($i=0;$i -lt 30;$i++) { docker exec $web test -f /tmp/sgpm-acceptance.json *> $null; if ($LASTEXITCODE -eq 0) { $webReady=$true; break }; Start-Sleep -Seconds 1 }
 if (!$webReady) { throw 'Clon HTTPS no disponible' }
 $performance=(& docker exec $source.web_container cat /workspace/storage/acceptance/performance.json) -join "`n"
 if ($LASTEXITCODE -ne 0 -or !$performance) { throw 'No se pudo recuperar metadata privada del fixture de carga' }
 $seedFile=Join-Path $private 'performance.json'
 [System.IO.File]::WriteAllText($seedFile,$performance,(New-Object System.Text.UTF8Encoding($false)))
 Checked-Docker -Arguments @('exec','--user','www-data',$web,'mkdir','-p','/workspace/storage/acceptance')
 Checked-Docker -Arguments @('cp',$seedFile,($web+':/tmp/sgpm-performance.json'))
 Checked-Docker -Arguments @('exec','--user','www-data',$web,'cp','/tmp/sgpm-performance.json','/workspace/storage/acceptance/performance.json')
 Copy-Item -LiteralPath (Join-Path $private 'server.crt') -Destination (Join-Path $directory 'server.crt')
 [System.IO.File]::WriteAllText((Join-Path $directory 'performance.json'),$performance,(New-Object System.Text.UTF8Encoding($false)))
 $metadata=@{database_container=$database;web_container=$web;url='https://localhost:28443';config_sha256=$source.config_sha256;config_owner=$false;fixture_email=$source.fixture_email;fixture_password=$source.fixture_password;storage_profile='Docker writable layer/anonymous volumes; C: NVMe SSD';source_database=$source.database_container}
 [System.IO.File]::WriteAllText((Join-Path $directory 'local.json'),($metadata | ConvertTo-Json),(New-Object System.Text.UTF8Encoding($false)))
 $ready=$true
 Write-Output 'Clon SSD desechable listo en https://localhost:28443; mismo certificado aceptado. Metadata privada: storage/acceptance/ssd/local.json.'
} finally {
 foreach ($key in $saved.Keys) { [Environment]::SetEnvironmentVariable($key,$saved[$key],'Process') }
 if (!$ready) { if ($createdWeb) { docker rm -fv $web | Out-Null }; if ($createdDb) { docker rm -fv $database | Out-Null } }
 # Nonrecursive cleanup of files created in the protected seed directory only.
 foreach ($file in @('server.crt','server.key','database.dump','roles.sql','performance.json')) { $path=Join-Path $private $file; if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path } }
 if (Test-Path -LiteralPath $private) { Remove-Item -LiteralPath $private }
}
