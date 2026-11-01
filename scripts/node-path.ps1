$nodeCommand = Get-Command node -ErrorAction SilentlyContinue
if ($nodeCommand) {
    $script:NodeExecutable = $nodeCommand.Source
} else {
    $script:NodeExecutable = Join-Path $env:USERPROFILE '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin/node.exe'
    if (-not (Test-Path -LiteralPath $script:NodeExecutable)) {
        throw 'Node.js 22.12+ is required. Install Node.js LTS, then try again.'
    }
}
