$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
$projectPath = (Get-Location).Path
. (Join-Path $PSScriptRoot 'node-path.ps1')
if (-not (Test-Path -LiteralPath 'node_modules/astro')) { throw 'Run scripts/setup.ps1 first.' }
New-Item -ItemType Directory -Force -Path '.local' | Out-Null
$backendRunning = $false
try {
    $response = Invoke-WebRequest -Uri 'http://127.0.0.1:9400/?rest_route=/wp/v2/posts' -TimeoutSec 3
    $backendRunning = ($response.StatusCode -eq 200)
} catch {}
$backendProcess = $null
try {
    if (-not $backendRunning) {
        $backendProcess = Start-Process -FilePath $NodeExecutable -ArgumentList 'scripts/wordpress.mjs' -WorkingDirectory $projectPath -WindowStyle Hidden -PassThru -RedirectStandardOutput '.local/wordpress.log' -RedirectStandardError '.local/wordpress-error.log'
        for ($attempt = 0; $attempt -lt 180; $attempt++) {
            if ($backendProcess.HasExited) { Get-Content '.local/wordpress-error.log'; throw 'WordPress startup failed.' }
            try {
                $response = Invoke-WebRequest -Uri 'http://127.0.0.1:9400/?rest_route=/wp/v2/posts' -TimeoutSec 2
                if ($response.StatusCode -eq 200) { $backendRunning = $true; break }
            } catch {}
            Start-Sleep -Seconds 1
        }
        if (-not $backendRunning) { throw 'WordPress startup timed out. See .local/wordpress.log.' }
    }
    & $NodeExecutable --env-file-if-exists=.env scripts/sync-content.mjs
    if ($LASTEXITCODE -ne 0) { throw 'WordPress content sync failed.' }
    Write-Host 'Frontend: http://127.0.0.1:4321'
    Write-Host 'WordPress: http://127.0.0.1:9400/wp-admin/'
    & $NodeExecutable scripts/astro.mjs dev --host 127.0.0.1
} finally {
    if ($null -ne $backendProcess -and -not $backendProcess.HasExited) { Stop-Process -Id $backendProcess.Id }
}
