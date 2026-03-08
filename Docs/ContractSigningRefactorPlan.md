# Plano de Refatoracao - Dashboard + Assinatura de Contrato

## Objetivo

Refatorar a aplicacao para dois fluxos principais:

1. `Dashboard` (acesso autenticado).
2. `Assinatura de contrato` (acesso do signatario sem login tradicional, via link seguro).

O trabalho sera executado em fases. Ao final de cada fase, eu paro e te peço confirmacao/teste antes de seguir para a proxima.

---

## Escopo funcional solicitado

- Dashboard deve exigir login.
- Tela de assinatura deve:
  - abrir com modal para coleta de dados (nome, email, cpf);
  - mostrar PDF para leitura;
  - permitir assinatura com fluxo de confirmacao e preview;
  - liberar botao final de conclusao apenas apos assinar;
  - no final, abrir modal para escolha do canal de recebimento do contrato;
  - concluir operacao com confirmacao final.
- Remover CTA "cadastre-se" do dashboard.
- Criar usuario inicial fixo para ambiente dev:
  - usuario: `admin`
  - senha: `admin`

---

## Arquitetura alvo (alto nivel)

### Rotas frontend

- `/dashboard` -> area autenticada.
- `/assinar/:token` -> fluxo de assinatura.
- `/login` -> autenticacao do dashboard.

### Backend (API)

- Autenticacao dashboard:
  - `POST /api/auth/login`
  - `POST /api/auth/logout`
  - `GET /api/auth/me`
- Assinatura por token:
  - `GET /api/signing/:token/context`
  - `POST /api/signing/:token/signer-data`
  - `POST /api/signing/:token/sign`
  - `POST /api/signing/:token/finalize`
- PDF:
  - manter endpoint de download/visualizacao do contrato.

### Banco (novas necessidades)

- Campo/token de assinatura por contrato (unico, seguro).
- Dados do signatario coletados no fluxo (nome, email, cpf).
- Metadados de assinatura (assinou, quando, canal escolhido).

---

## Fases de execucao (com checkpoints)

## Fase 1 - Base de autenticacao + seed admin

### Entregas

- Backend:
  - implementar login/logout/me para dashboard.
  - proteger rotas de dashboard por middleware auth.
  - criar seeder com usuario `admin/admin` (somente dev).
- Frontend:
  - criar tela `/login`.
  - salvar sessao/token.
  - proteger acesso a `/dashboard`.
  - remover botao/acao "cadastre-se" do contexto do dashboard.

### Teste manual esperado

1. Acessar `/dashboard` sem login deve redirecionar para `/login`.
2. Login com `admin/admin` deve funcionar.
3. Logout deve encerrar sessao.

### Checkpoint

Parar aqui e pedir sua validacao.

---

## Fase 2 - Fundacao da rota de assinatura por token

### Entregas

- Backend:
  - adicionar token de assinatura no contrato.
  - endpoint de contexto do link (`/assinar/:token`).
  - validacoes de token invalido/expirado.
- Frontend:
  - criar pagina `/assinar/:token`.
  - carregar contexto inicial do contrato.

### Teste manual esperado

1. Link valido abre pagina de assinatura.
2. Link invalido mostra estado de erro amigavel.

### Checkpoint

Parar e pedir sua validacao.

---

## Fase 3 - Modal inicial de dados do signatario

### Entregas

- Ao abrir `/assinar/:token`, exibir modal bloqueante com:
  - nome
  - email
  - cpf
- Persistir no backend os dados enviados.
- Nao liberar leitura/acao sem preencher dados validos.

### Teste manual esperado

1. Sem preencher dados, usuario nao avanca.
2. Com dados validos, modal fecha e fluxo continua.

### Checkpoint

Parar e pedir sua validacao.

---

## Fase 4 - Visualizacao do PDF + ponto de assinatura

### Entregas

- Renderizar PDF na pagina de assinatura (viewer).
- Destacar area de assinatura.
- Botao de acao sobre a area de assinatura.

### Teste manual esperado

1. PDF visivel e legivel.
2. Botao de assinatura posicionado no bloco da assinatura.

### Checkpoint

Parar e pedir sua validacao.

---

## Fase 5 - Modal de confirmacao da assinatura + preview

### Entregas

- Clique em "assinar" abre modal de confirmacao.
- Mostrar preview da assinatura.
- Confirmacao salva assinatura no backend.
- Liberar botao "Finalizar assinatura do contrato" somente apos assinatura.

### Teste manual esperado

1. Sem assinatura, botao final fica desabilitado.
2. Apos confirmar assinatura, botao final fica habilitado.

### Checkpoint

Parar e pedir sua validacao.

---

## Fase 6 - Finalizacao + escolha de canal de recebimento

### Entregas

- Clique em "Finalizar assinatura do contrato" abre modal com opcoes de recebimento.
- Persistir escolha do canal.
- Concluir fluxo com mensagem de sucesso.

### Teste manual esperado

1. Escolha de canal obrigatoria para concluir.
2. Finalizacao marca contrato como assinado/concluido no sistema.

### Checkpoint

Parar e pedir sua validacao.

---

## Fase 7 - Ajustes finais de UX + QA

### Entregas

- Revisar UX dos modais e estados de erro/loading.
- Revisar fluxo completo dashboard + assinatura.
- Ajustar Swagger para novos endpoints.
- Atualizar README com novo fluxo.

### Teste manual esperado

1. Fluxo completo executa sem quebra.
2. Erros mostram mensagens claras.

### Checkpoint

Parar e pedir sua validacao final.

---

## Ordem de implementacao

1. Fase 1
2. Fase 2
3. Fase 3
4. Fase 4
5. Fase 5
6. Fase 6
7. Fase 7

Nao avancar de fase sem sua confirmacao.

---

## Observacoes de seguranca (importante)

- Credenciais `admin/admin` devem ser restritas ao ambiente de desenvolvimento.
- Em producao, o seed deve exigir troca imediata de senha ou ser desativado.
- Link de assinatura deve ser unico e dificil de adivinhar (token forte).
