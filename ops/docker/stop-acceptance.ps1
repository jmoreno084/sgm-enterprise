$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$metadataPath = Join-Path $repoRoot 'storage/acceptance/local.json'
if (!(Test-Path -LiteralPath $metadataPath)) { throw 'No hay entorno de aceptacion local registrado.' }
$metadata = Get-Content -LiteralPath $metadataPath -Raw | ConvertFrom-Json
# Validate every target before removing only disposable containers created by our runner.
foreach ($name in @($metadata.web_container, $metadata.database_container)) {
    if ($name -notmatch '^sgpm-foundation-[a-f0-9]{12}(-web)?$') { throw 'Nombre de contenedor fuera del entorno de pruebas.' }
    $labelJson = & docker inspect $name --format '{{json .Config.Labels}}' 2>$null
    if ($LASTEXITCODE -eq 0) {
        $labels = $labelJson | ConvertFrom-Json
        if ($labels.'sgpm.acceptance' -ne 'true') { throw 'El contenedor no pertenece al runner de aceptacion.' }
    }
}
foreach ($name in @($metadata.web_container, $metadata.database_container)) {
    & docker inspect $name *> $null
    if ($LASTEXITCODE -eq 0) {
        & docker rm -f $name | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo retirar un contenedor temporal.' }
    }
}
$configPath = Join-Path $repoRoot 'config/config.php'
if (Test-Path -LiteralPath $configPath) {
    if ((Get-FileHash -LiteralPath $configPath -Algorithm SHA256).Hash -eq $metadata.config_sha256) { Remove-Item -LiteralPath $configPath }
    else { Write-Warning 'Se conserva config/config.php porque fue modificado despues de iniciar las pruebas.' }
}
$certificatePath = Join-Path $repoRoot 'storage/acceptance/server.crt'
if (Test-Path -LiteralPath $certificatePath) { Remove-Item -LiteralPath $certificatePath }
Remove-Item -LiteralPath $metadataPath
Write-Output 'Entorno de aceptacion local retirado; enterprise intacta.'
