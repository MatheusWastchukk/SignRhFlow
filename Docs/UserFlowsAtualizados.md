# Fluxo Atualizado de Usuarios - SignRhFlow

## Objetivo

Este documento descreve o fluxo oficial atual do aplicativo para todos os perfis de uso, considerando:

- Dashboard administrativo autenticado.
- Assinatura oficial via Autentique (fonte de verdade).
- Processamento assincrono com fila e webhook.

---

## Perfis de usuario

## 1) Administrador (RH/Operacao)

Responsavel por criar contratos, acompanhar status e gerenciar dados no dashboard.

### 1.1 Login e acesso

1. Acessa `/login`.
2. Autentica com usuario/senha.
3. Acessa `/dashboard` com token de sessao.

### 1.2 Criacao de contrato

1. Vai em `Novo contrato`.
2. Informa dados do colaborador (nome, email, telefone, CPF, metodo de entrega).
3. Sistema cria/atualiza colaborador e cria contrato.
4. Backend gera PDF local e coloca contrato como `PENDING`.
5. Job `SendContractToAutentique` envia o documento para a Autentique.
6. Ao sucesso, sistema salva:
   - `autentique_document_id`
   - `autentique_signing_url` (link oficial de assinatura)

### 1.3 Acompanhamento no dashboard

No dashboard, o admin acompanha:

- Status do contrato (`PENDING`, `SIGNED`, `REJECTED`).
- Datas de geracao e assinatura.
- ID do documento na Autentique.
- Link de assinatura (prioriza link oficial da Autentique quando disponivel).

### 1.4 Acoes administrativas

Na tabela de contratos:

- **Editar** (lapis): altera dados do contrato e colaborador.
- **Visualizar** (olho): abre modal de detalhes.
- **Excluir** (lixeira): remove contrato.

---

## 2) Signatario (colaborador que assina)

Usuario externo que recebe o link e realiza assinatura.

### 2.1 Acesso ao link

1. Entra em `/assinar/{token}`.
2. Sistema valida token e expiracao.
3. Modal inicial exige dados (nome, email, CPF, telefone e pais).
4. Enquanto modal inicial esta aberto, a pagina fica com scroll bloqueado.

### 2.2 Validacao de identidade

1. Dados preenchidos sao enviados para o backend.
2. Backend compara com os dados do colaborador do contrato.
3. Em caso de divergencia, retorna erro especifico por campo.

### 2.3 Assinatura oficial

1. Signatario clica em **Assinar na Autentique**.
2. Sistema abre `autentique_signing_url` em nova aba.
3. A assinatura oficial ocorre no ambiente da Autentique.
4. Ao retornar para a tela, o usuario pode clicar em **Atualizar status**.

### 2.4 Finalizacao local do fluxo

1. Signatario escolhe canal de recebimento.
2. Sistema finaliza solicitacao local mantendo status coerente com a Autentique.
3. Contrato so vira `SIGNED` oficialmente quando webhook confirmar evento de assinatura.

---

## 3) Integracao Autentique (sistema externo)

Fonte de verdade da assinatura juridica/oficial.

### 3.1 Envio de documento

- Integracao usa GraphQL multipart (`operations`, `map`, `file`).
- Mutation de criacao usa `document`, `signers` e `file`.
- Sistema persiste `autentique_document_id` e link de assinatura.

### 3.2 Tratamento de credito LIVE

- Se a API retornar `unavailable_verifications_credits`, o sistema faz fallback sem `LIVE` para nao travar o envio.

### 3.3 Webhook

- Endpoint: `POST /api/webhooks/autentique`.
- Suporta formatos diferentes de payload (campos novos e legados).
- Job `ProcessWebhookJob` processa idempotente e atualiza status:
  - `SIGNED` quando evento de assinatura e confirmado.
  - `REJECTED` quando evento de rejeicao/recusa e confirmado.
  - `PENDING` para eventos de entrega/visualizacao/pendencia.

---

## Significado dos status

- `PENDING`: contrato criado/enviado, aguardando assinatura oficial.
- `SIGNED`: assinatura confirmada pela Autentique (webhook).
- `REJECTED`: recusa/cancelamento confirmado por evento.
- `DRAFT`: estado legado; nao deve ser o fluxo padrao atual.

---

## Regras importantes

1. A assinatura oficial nao e mais simulada localmente no frontend.
2. O painel da Autentique e o webhook sao a referencia final do status de assinatura.
3. O admin pode monitorar e corrigir dados, mas nao deve forcar conclusao sem evento oficial.

---

## Checklist operacional rapido

1. Queue worker ativo (`contracts,webhooks`).
2. Token e URL da Autentique configurados.
3. Contrato novo deve entrar em `PENDING`.
4. `autentique_document_id` deve ser preenchido apos envio.
5. Assinatura no portal Autentique deve refletir via webhook no dashboard.

