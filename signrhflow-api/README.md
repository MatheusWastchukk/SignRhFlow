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
AUTENTIQUE_API_TOKEN=
AUTENTIQUE_GRAPHQL_URL=https://api.autentique.com.br/v2/graphql
AUTENTIQUE_WEBHOOK_SECRET=
API_RATE_LIMIT_PER_MINUTE=120
WEBHOOK_RATE_LIMIT_PER_MINUTE=300
METRICS_TOKEN=
```

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
