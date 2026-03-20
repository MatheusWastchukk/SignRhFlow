# Comandos usando só Docker (sem PHP/Node no PC)

Você precisa apenas de **Docker Desktop** (Windows) ou Docker Engine + Compose.  
Execute os comandos na **raiz do repositório** (`SignRhFlow`), onde está o `docker-compose.yml`.

## 1. Subir o ambiente

```powershell
docker compose up -d
```

Aguarde o serviço `api` ficar *healthy* (pode levar ~2 min na primeira vez por causa do `composer install` no `CMD`).

---

## 2. Testes e lint da API (Laravel)

### Opção A — com a stack já rodando (recomendado)

```powershell
docker compose exec api bash -lc "composer install --no-interaction && composer run lint && php artisan test"
```

### Opção B — um container avulso (sem subir o `serve` na porta 8000)

Útil se você **não** quiser manter o serviço `api` no ar:

```powershell
docker compose run --rm --no-deps api bash -lc "composer install --no-interaction && composer run lint && php artisan test"
```

> `--no-deps` evita subir Postgres/Redis; os testes usam SQLite em memória (`phpunit.xml`).

### Outros comandos úteis na API

```powershell
docker compose exec api php artisan l5-swagger:generate
docker compose exec api php artisan migrate
docker compose exec api php artisan test --filter=HealthCheckTest
```

---

## 3. Build do front (Angular)

O container `web` no compose fica preso no `ng serve`. Para **só compilar**, use um `run` avulso:

```powershell
docker compose run --rm --no-deps web bash -lc "npm ci && npm run build"
```

### Testes unitários do front (`ng test`)

O `ng test` precisa do **Chrome/Chromium**. A imagem `node:20` não traz navegador. Opções:

1. **Conferir no CI** — o GitHub Actions já roda Karma com Chrome ([`.github/workflows/ci.yml`](../.github/workflows/ci.yml)).
2. **Um comando avulso com Chromium** (instala no container como root):

```powershell
docker compose run --rm --no-deps -u root web bash -lc "apt-get update && apt-get install -y chromium && cd /app && npm ci && CHROME_BIN=/usr/bin/chromium npx ng test --no-watch --no-progress --browsers=ChromeHeadless"
```

Se falhar por sandbox, use o CI ou rode os testes em uma máquina com Node instalado.

---

## 4. Scripts na pasta `scripts/` (PowerShell)

Na raiz do repo:

```powershell
.\scripts\docker-api-test.ps1
.\scripts\docker-web-build.ps1
```

(Política de execução: `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned` se o Windows bloquear.)

---

## 5. Health / manual

Com a API no ar:

```powershell
curl http://localhost:8000/api/health
curl http://localhost:8000/up
```

Detalhes: [`Healthchecks.md`](Healthchecks.md).

---

## 6. Rebuild da API após mudança no Dockerfile

Se você alterou o `Dockerfile` (ex.: nova extensão PHP):

```powershell
docker compose build api queue
docker compose up -d
```
