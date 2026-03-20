# Healthchecks (passo 1)

## Endpoints

| Tipo        | URL            | Uso |
|------------|----------------|-----|
| **Liveness**  | `GET /up`      | Laravel confirma que o PHP/app sobem (sem checar DB/Redis). |
| **Readiness** | `GET /api/health` | JSON com `database` + `redis`; **503** se alguma dependência falhar. |

### Exemplo de resposta OK (200)

```json
{
  "status": "ok",
  "checks": {
    "database": "ok",
    "redis": "ok"
  }
}
```

### Exemplo degradado (503)

```json
{
  "status": "unhealthy",
  "checks": {
    "database": "ok",
    "redis": "fail"
  }
}
```

## Docker Compose

- **db**: `pg_isready -U root -d signrhflow`
- **redis**: `redis-cli ping`
- **api**: `curl -fsS http://127.0.0.1:8000/api/health`
- **queue**: só sobe depois de `api`, `db` e `redis` estarem *healthy*

> O serviço `api` tem `start_period: 120s` porque o `CMD` atual roda `composer install` na subida.

## Casos de teste manuais (revisão)

1. **Stack no ar**: `docker compose up -d` → aguardar `api` healthy → `curl http://localhost:8000/api/health` → 200 e ambos `ok`.
2. **Redis caído**: parar só o Redis → chamar `/api/health` → 503 e `redis: fail` (ou indisponível se a API também cair).
3. **DB caído**: parar só o Postgres → 503 e `database: fail`.
4. **Liveness**: `curl http://localhost:8000/up` → 200 mesmo com dependências ruins (útil só para “processo vivo”).

## Testes automatizados

Com Docker (na raiz do monorepo):

```powershell
docker compose exec api php artisan test --filter=HealthCheckTest
```

Ou avulso, sem manter o `serve` no ar:

```powershell
docker compose run --rm --no-deps api bash -lc "composer install --no-interaction && php artisan test --filter=HealthCheckTest"
```

Ver também [`ComandosDocker.md`](ComandosDocker.md).
