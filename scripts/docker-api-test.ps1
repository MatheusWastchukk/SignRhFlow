# Testes + Pint da API via Docker (raiz do monorepo).
# Uso: .\scripts\docker-api-test.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

# docker compose envia avisos ao stderr; no PowerShell isso vira erro se capturarmos direto com $ErrorActionPreference Stop.
$apiRunning = ([string](cmd /c "docker compose ps -q api 2>nul")).Trim()
$ErrorActionPreference = "SilentlyContinue"

if ($apiRunning.Length -gt 0) {
    Write-Host "Usando container 'api' em execucao (docker compose exec)..." -ForegroundColor Cyan
    docker compose exec -T api bash -lc "composer install --no-interaction && composer run lint && php artisan test"
} else {
    Write-Host "Nenhum container 'api' rodando; usando docker compose run --no-deps..." -ForegroundColor Cyan
    docker compose run --rm --no-deps api bash -lc "composer install --no-interaction && composer run lint && php artisan test"
}

if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
