# SignRhFlow API

API Laravel para fluxo de admissao com:

- cadastro de colaboradores;
- geracao de contrato em PDF;
- envio assíncrono para assinatura na Autentique;
- processamento de webhooks com idempotência;
- documentacao via Swagger.

## Arquitetura resumida

### Backend
- Laravel 12 + PostgreSQL + Redis.
- Filas para envio (`SendContractToAutentique`) e processamento de webhook (`ProcessWebhookJob`).
- Serviço isolado para integração externa (`AutentiqueService`).

### Fluxo principal
1. `POST /api/employees` cria colaborador.
2. `POST /api/contracts` cria contrato, gera PDF e despacha job de envio.
3. Job envia arquivo para Autentique e salva `autentique_document_id`.
4. `POST /api/webhooks/autentique` recebe evento, grava log e processa em background.
5. Status é atualizado no contrato (`DRAFT`, `PENDING`, `SIGNED`, `REJECTED`).

## Como subir localmente

Com Docker Compose na raiz do monorepo:

```bash
docker compose up -d
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate
```

## Variáveis de ambiente importantes

No `.env` da API:

```env
APP_URL=http://localhost:8000
QUEUE_CONNECTION=redis

AUTENTIQUE_API_TOKEN=
AUTENTIQUE_GRAPHQL_URL=https://api.autentique.com.br/v2/graphql
AUTENTIQUE_WEBHOOK_SECRET=
```

## Documentação de API (Swagger)

- UI: `http://localhost:8000/api/documentation`
- Gerar docs:

```bash
docker compose exec api php artisan l5-swagger:generate
```

## Execução de worker de fila

```bash
docker compose exec api php artisan queue:work --queue=contracts,webhooks
```

## Testes

```bash
docker compose exec api php artisan test
```

## Decisões técnicas

- PDF é persistido em `storage/app/contracts`.
- Envio para Autentique é sempre assíncrono.
- Limite de 60 req/min é aplicado com `RateLimiter` e `release(delay)`.
- Webhook usa hash do payload para idempotência (`event_hash` único).
- Estados terminais (`SIGNED`, `REJECTED`) não regredem em eventos fora de ordem.
