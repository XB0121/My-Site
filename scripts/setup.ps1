$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
. (Join-Path $PSScriptRoot 'node-path.ps1')
if (-not (Test-Path -LiteralPath '.tools/package/bin/npm-cli.js')) {
    New-Item -ItemType Directory -Force -Path '.tools' | Out-Null
    Invoke-WebRequest -Uri 'https://registry.npmjs.org/npm/-/npm-12.2.1.tgz' -OutFile '.tools/npm.tgz'
    tar -xzf '.tools/npm.tgz' -C '.tools'
    if ($LASTEXITCODE -ne 0) { throw 'Could not extract npm.' }
}
& $NodeExecutable '.tools/package/bin/npm-cli.js' ci --cache=.tools/npm-cache --no-fund --no-audit
if ($LASTEXITCODE -ne 0) { throw 'Dependency installation failed.' }
if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
Write-Host 'Setup complete. Run scripts/start-dev.ps1.'
