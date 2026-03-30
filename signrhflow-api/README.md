# SignRhFlow API (Laravel)

Visão geral do produto e diagramas: [**README na raiz**](../README.md).  
**Guia linha a linha do backend (iniciantes):** [`Docs/BACKEND_GUIDE_PT.md`](../Docs/BACKEND_GUIDE_PT.md).

## Subir com Docker (na raiz do monorepo)

```bash
docker compose up -d
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate
```

## Worker de filas

```bash
docker compose exec api php artisan queue:work --queue=contracts,webhooks
```

## Variáveis `.env` (essenciais)

```env
APP_URL=http://localhost:8000
QUEUE_CONNECTION=redis
REDIS_HOST=redis
# Recomendado com Docker: throttle da API usa cache (não Redis), evitando 500 se Redis falhar só para rate limit.
CACHE_STORE=database
AUTENTIQUE_API_TOKEN=
AUTENTIQUE_GRAPHQL_URL=https://api.autentique.com.br/v2/graphql
AUTENTIQUE_WEBHOOK_SECRET=   # secreto exibido uma vez ao salvar o endpoint no painel Autentique; vazio = não valida X-Autentique-Signature
AUTENTIQUE_WEBHOOK_HANDLE_SYNC=false   # true em dev: processa webhook na hora (sem depender do worker); produção = false
API_RATE_LIMIT_PER_MINUTE=120
WEBHOOK_RATE_LIMIT_PER_MINUTE=300
METRICS_TOKEN=
```

### Erro `getaddrinfo for redis failed`

- Dentro do Compose, use **`REDIS_HOST=redis`** e suba o serviço **`redis`** na **mesma rede** que `api` e `queue`.
- Se `CACHE_STORE=redis`, qualquer throttle (incluindo `GET /api/health`) tenta Redis antes da lógica da rota — use **`CACHE_STORE=database`** (tabela `cache` já existe nas migrations) ou garanta Redis acessível.
- O `docker-compose.yml` do repo força `CACHE_STORE=database` e `REDIS_HOST=redis` nos serviços `api` e `queue` para reduzir esse tipo de falha.

## Swagger

```bash
docker compose exec api php artisan l5-swagger:generate
```

UI: `http://localhost:8000/api/documentation`

## Testes e lint

```bash
docker compose exec api composer run lint
docker compose exec api php artisan test
```

## Endpoints úteis

- `GET /api/health` — Postgres + Redis  
- `GET /api/metrics` — se `METRICS_TOKEN` definido; header `Authorization: Bearer …`
