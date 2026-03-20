# Build do Angular via Docker (sem Node no PC).
# Uso: .\scripts\docker-web-build.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

docker compose run --rm --no-deps web bash -lc "npm ci && npm run build"
