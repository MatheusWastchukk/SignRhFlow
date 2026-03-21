# SignRhFlow Web (Angular 17)

SPA do dashboard e do fluxo de assinatura. Visão geral do monorepo: [**README na raiz**](../README.md).

## Desenvolvimento

```bash
cd signrhflow-web
npm ci
ng serve
```

Abra `http://localhost:4200/`. A API padrão aponta para `http://localhost:8000/api` (veja `src/app/services/api.service.ts`).

## Build

```bash
npm run build
```

## Testes unitários

```bash
npm test
```

## Docker (sem Node local)

Na raiz do repositório:

```powershell
docker compose run --rm --no-deps web bash -lc "npm ci && npm run build"
```

Ou: `.\scripts\docker-web-build.ps1` — detalhes em [`../Docs/ComandosDocker.md`](../Docs/ComandosDocker.md).
