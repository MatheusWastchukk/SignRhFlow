# Passo 3 — Dashboard sem edição local

A UI não oferece mais “editar contrato”: alterações de status/dados precisam refletir a **Autentique** e o **backend** (webhook, fila), não um PATCH só no painel.

## O que mudou

- Removidos o botão de lápis e o modal de edição no dashboard.
- Mantidos: **ver detalhes** (modal), **excluir**, links de PDF e Autentique.
- Removido `updateContract` do `ApiService` (o `PATCH /api/contracts/{id}` continua existindo na API Laravel, se quiser usar outro cliente/admin).

## Casos de teste (manuais)

1. Abrir **Dashboard** logado → na coluna **Ações** aparecem só **ver** (olho) e **excluir** (lixeira), **sem** ícone de editar.
2. Clicar em **ver** → modal de detalhes abre e fecha com **Fechar**.
3. Clicar em **excluir** → confirmação → contrato some da lista e mensagem verde de sucesso.
4. Criar contrato em **Novo contrato** → fluxo continua igual (criar não foi removido).

## Automatizado

- `npm run build` / `ng test` — sem referências a `updateContract` no front.
