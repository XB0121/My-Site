$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
. (Join-Path $PSScriptRoot 'node-path.ps1')
$backendProcess = $null
try {
    $backendRunning = $false
    try { $backendRunning = ((Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:9400/?rest_route=/vibe-site/v1/content' -TimeoutSec 3).StatusCode -eq 200) } catch {}
    if (-not $backendRunning) {
        New-Item -ItemType Directory -Force -Path '.local' | Out-Null
        $backendProcess = Start-Process -FilePath $NodeExecutable -ArgumentList 'scripts/wordpress.mjs' -WorkingDirectory (Get-Location).Path -WindowStyle Hidden -PassThru -RedirectStandardOutput '.local/publish-backend.log' -RedirectStandardError '.local/publish-backend-error.log'
        for ($attempt = 0; $attempt -lt 180; $attempt++) {
            if ($backendProcess.HasExited) { throw 'WordPress could not start. Check .local/publish-backend-error.log.' }
            try { if ((Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:9400/?rest_route=/vibe-site/v1/content' -TimeoutSec 2).StatusCode -eq 200) { $backendRunning = $true; break } } catch {}
            Start-Sleep -Seconds 1
        }
        if (-not $backendRunning) { throw 'WordPress startup timed out.' }
    }
    # Content sync uses an inter-process lock shared with the live preview watcher.
    & $NodeExecutable --env-file-if-exists=.env scripts/sync-content.mjs
    if ($LASTEXITCODE -ne 0) { throw 'Website content sync failed. Nothing was published.' }
    $env:SITE_URL = 'https://xb0121.github.io'
    $env:BASE_PATH = '/My-Site'
    & $NodeExecutable scripts/astro.mjs build
    if ($LASTEXITCODE -ne 0) { throw 'Build failed. Nothing was published.' }
    git add -- src/data
    if (Test-Path -LiteralPath 'public/media') { git add -- public/media }
    if ($LASTEXITCODE -ne 0) { throw 'Could not stage website content.' }
    git diff --cached --quiet
    if ($LASTEXITCODE -eq 1) {
        git commit -m 'Update website content from WordPress'
        if ($LASTEXITCODE -ne 0) { throw 'Could not commit website content.' }
    } elseif ($LASTEXITCODE -ne 0) { throw 'Could not check website changes.' }
    Write-Host 'Uploading to GitHub. On first use, GitHub may ask you to sign in through your browser.'
    git push origin HEAD:main
    if ($LASTEXITCODE -ne 0) { throw 'GitHub upload failed. Your local content is saved. Check sign-in or ask Codex to resolve the remote changes, then try again.' }
    Write-Host 'Uploaded. GitHub will build and deploy the site in about 1-2 minutes.'
    Write-Host 'Website: https://xb0121.github.io/My-Site/'
} finally {
    if ($null -ne $backendProcess -and -not $backendProcess.HasExited) { Stop-Process -Id $backendProcess.Id }
}
