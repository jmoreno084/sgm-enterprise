param([switch]$Diagnostic,[string]$Metadata='storage/acceptance/local.json',[string]$EvidencePrefix='performance',[string[]]$Rounds=@())
$ErrorActionPreference='Stop'
$root=[System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$metadataPath=[System.IO.Path]::GetFullPath((Join-Path $root $Metadata))
$acceptanceRoot=[System.IO.Path]::GetFullPath((Join-Path $root 'storage/acceptance'))+[System.IO.Path]::DirectorySeparatorChar
if (!$metadataPath.StartsWith($acceptanceRoot,[System.StringComparison]::OrdinalIgnoreCase) -or $EvidencePrefix -notmatch '^[a-z0-9-]+$') { throw 'Metadata/evidencia fuera de la revisión local' }
$metadataDirectory=Split-Path $metadataPath
$meta=Get-Content -Raw $metadataPath | ConvertFrom-Json
$sessionsPath=Join-Path $metadataDirectory 'performance-sessions.json'
$sessionsRelative=$sessionsPath.Substring($root.Length+1).Replace('\','/')
if ($meta.database_container -notmatch '^sgpm-foundation-[a-f0-9]{12}$' -or $meta.web_container -ne ($meta.database_container+'-web')) { throw 'Entorno desechable no autorizado' }
foreach ($name in @($meta.database_container,$meta.web_container)) {
 $labels=docker inspect $name --format '{{json .Config.Labels}}' | ConvertFrom-Json
 if ($LASTEXITCODE -ne 0 -or $labels.'sgpm.acceptance' -ne 'true') { throw 'Falta etiqueta de aislamiento' }
}
$networks=docker inspect $meta.database_container --format '{{json .NetworkSettings.Networks}}' | ConvertFrom-Json
$address=$networks.bridge.IPAddress
if ($address -notmatch '^172\.[0-9]+\.[0-9]+\.[0-9]+$') { throw 'Direccion interna inesperada; revisar configuracion' }
$image=docker inspect $meta.web_container --format '{{.Image}}'
if ($LASTEXITCODE -ne 0 -or !$image) { throw 'Imagen de pruebas ausente' }
$evidence=Join-Path $root 'ops/evidence'
$shaped=$false
try {
 # Sidecar changes only the isolated target's eth0; PHP-to-DB loopback is untouched.
 docker run --rm --pull=never --cap-add NET_ADMIN --network ('container:'+$meta.database_container) --entrypoint /sbin/tc $image qdisc add dev eth0 root netem delay 25ms rate 20mbit
 if ($LASTEXITCODE -ne 0) { throw 'No se pudo configurar red del objetivo desechable' }
 $shaped=$true
 $selectedRounds=if ($Diagnostic) { @('diagnostic-shaped') } elseif ($Rounds.Count -gt 0) { $Rounds } else { @('cold-controlled-1','cold-controlled-2','cold-controlled-3','warm-1','warm-2','warm-3') }
 foreach ($round in $selectedRounds) {
  if ($round -notmatch '^(cold-controlled-[1-3]|warm-[1-3]|diagnostic-shaped)$') { throw 'Ronda no reconocida' }
  $output=Join-Path $evidence ($EvidencePrefix+'-'+$round+'.json')
  if (Test-Path -LiteralPath $output) { throw 'Existe evidencia de esta ronda; archivarla antes de repetir para conservar fallos' }
  # Native fixture sessions are prepared outside warmup/measurement, so an idle
  # development interval cannot contaminate load results with expired cookies.
  docker exec --user www-data $meta.web_container php tests/fixtures/performance/sessions.php
  if ($LASTEXITCODE -ne 0) { throw 'No se pudieron preparar las50 sesiones' }
  $privateSessions=docker exec $meta.web_container cat /workspace/storage/acceptance/performance-sessions.json
  if ($LASTEXITCODE -ne 0) { throw 'No se pudo recuperar metadata privada' }
  [System.IO.File]::WriteAllText($sessionsPath,($privateSessions -join "`n"),(New-Object System.Text.UTF8Encoding($false)))
  $args=@('run','--rm','--pull=never','--cap-add','NET_ADMIN','--cpus','4','--memory','8g','--label','sgpm.acceptance=true',
   '--mount',"type=bind,source=$root,target=/workspace,readonly",'--mount',"type=bind,source=$evidence,target=/workspace/ops/evidence",'--workdir','/workspace',
   '--entrypoint','/bin/sh',$image,'tests/performance/client.sh','--users=50',('--resolve='+$address),('--url='+$meta.url),('--sessions=/workspace/'+$sessionsRelative),('--ca=/workspace/'+$metadataDirectory.Substring($root.Length+1).Replace('\','/')+'/server.crt'),('--round='+$round),('--output=/workspace/ops/evidence/'+$EvidencePrefix+'-'+$round+'.json'),'--network-profile=netem-20Mbps-RTT50ms')
  if ($Diagnostic) { $args+=@('--warmup=10','--duration=20','--diagnostic') } else { $args+=@('--warmup=120','--duration=600') }
  Write-Output ('Inicio de ronda '+$round+';50 sesiones distintas, red20Mbps/RTT50ms.')
  & docker @args
  if ($LASTEXITCODE -ne 0) { throw ('Ronda no conforme: '+$round+'. Se conserva evidencia; corregir antes de repetir.') }
 }
} finally {
 if ($shaped) { docker run --rm --pull=never --cap-add NET_ADMIN --network ('container:'+$meta.database_container) --entrypoint /sbin/tc $image qdisc del dev eth0 root | Out-Null }
}
