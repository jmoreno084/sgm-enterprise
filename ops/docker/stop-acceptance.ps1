param([string]$Metadata='storage/acceptance/local.json')
$ErrorActionPreference = 'Stop'
$repoRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$metadataPath = [System.IO.Path]::GetFullPath((Join-Path $repoRoot $Metadata))
$acceptanceRoot=[System.IO.Path]::GetFullPath((Join-Path $repoRoot 'storage/acceptance'))+[System.IO.Path]::DirectorySeparatorChar
if (!$metadataPath.StartsWith($acceptanceRoot,[System.StringComparison]::OrdinalIgnoreCase)) { throw 'Metadata fuera del entorno de pruebas' }
$metadataDirectory=Split-Path $metadataPath
if (!(Test-Path -LiteralPath $metadataPath)) { throw 'No hay entorno de aceptacion local registrado.' }
$acceptanceMetadata = Get-Content -LiteralPath $metadataPath -Raw | ConvertFrom-Json
# Validate every target before removing only disposable containers created by our runner.
foreach ($name in @($acceptanceMetadata.web_container, $acceptanceMetadata.database_container)) {
    if ($name -notmatch '^sgpm-foundation-[a-f0-9]{12}(-web)?$') { throw 'Nombre de contenedor fuera del entorno de pruebas.' }
    $labelJson = & docker inspect $name --format '{{json .Config.Labels}}' 2>$null
    if ($LASTEXITCODE -eq 0) {
        $labels = $labelJson | ConvertFrom-Json
        if ($labels.'sgpm.acceptance' -ne 'true') { throw 'El contenedor no pertenece al runner de aceptacion.' }
    }
}
foreach ($name in @($acceptanceMetadata.web_container, $acceptanceMetadata.database_container)) {
    & docker inspect $name *> $null
    if ($LASTEXITCODE -eq 0) {
        & docker rm -fv $name | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo retirar un contenedor temporal.' }
    }
}
$configPath = Join-Path $repoRoot 'config/config.php'
if ($acceptanceMetadata.config_owner -ne $false -and (Test-Path -LiteralPath $configPath)) {
    if ((Get-FileHash -LiteralPath $configPath -Algorithm SHA256).Hash -eq $acceptanceMetadata.config_sha256) { Remove-Item -LiteralPath $configPath }
    else { Write-Warning 'Se conserva config/config.php porque fue modificado despues de iniciar las pruebas.' }
}
$certificatePath = Join-Path $metadataDirectory 'server.crt'
if (Test-Path -LiteralPath $certificatePath) { Remove-Item -LiteralPath $certificatePath }
Remove-Item -LiteralPath $metadataPath
foreach ($file in @('performance.json','performance-sessions.json','inspect-map.py')) { $path=Join-Path $metadataDirectory $file; if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path } }
Write-Output 'Entorno de aceptacion local retirado; enterprise intacta.'
