# Plano de Execucao do SignRhFlow

## 1) Objetivo do projeto

Construir um portal corporativo de RH para admissao de colaboradores com fluxo ponta a ponta:

- cadastro de colaborador;
- geracao de contrato em PDF;
- envio para assinatura digital via API da Autentique;
- atualizacao de status assincronamente por webhook no backend;
- exibicao de status no frontend em tempo quase real.

## 2) Escopo tecnico consolidado

- Backend: PHP 8.x + Laravel 11 (API REST isolada).
- Frontend: Angular 17+ (SPA).
- Banco: PostgreSQL.
- Filas e limite de taxa: Redis.
- Infra local: Docker (Laravel Sail).
- CI/CD simulado: GitHub Actions com testes de qualidade e seguranca.

## 3) Pre-requisitos antes de codar

1. Docker Desktop instalado e funcionando.
2. Git instalado.
3. Conta Sandbox na Autentique com token de API.
4. Portas livres: `8000` (API), `4200` (Angular), `5432` (Postgres), `6379` (Redis).
5. Projeto clonado e pastas principais disponiveis:
  - `signrhflow-api/`
  - `signrhflow-web/`
  - `Docs/`

## 4) Passo a passo de implementacao (ordem recomendada)

### Fase 1 - Infraestrutura e setup inicial

1. Criar backend Laravel com Sail e servicos `pgsql` e `redis`.
2. Subir containers com Sail em background.
3. Configurar `.env` da API com:
  - `AUTENTIQUE_API_TOKEN`
  - `AUTENTIQUE_GRAPHQL_URL=https://api.autentique.com.br/v2/graphql`
  - credenciais de banco e redis.
4. Criar frontend Angular e garantir execucao na porta `4200`.
5. Validar conectividade:
  - API responde em `http://localhost:8000`.
  - Frontend responde em `http://localhost:4200`.

### Fase 2 - Banco de dados e dominio principal

1. Criar models e migrations para:
  - `Employee`
  - `Contract`
  - `WebhookLog`
2. Implementar modelagem com integridade:
  - UUID para entidades principais;
  - chaves estrangeiras;
  - indices para consultas frequentes;
  - `softDeletes()` onde exigido.
3. Estruturar tabelas:
  - `users`: gestor de RH.
  - `employees`: colaborador (email/cpf unicos).
  - `contracts`: vinculo com colaborador, status, metodo de entrega, id externo da Autentique.
  - `webhook_logs`: auditoria do payload, controle de processamento e erro.
4. Rodar migrations e confirmar estrutura no banco.

### Fase 3 - Endpoints REST e validacao robusta

1. Criar controllers:
  - `EmployeeController`
  - `ContractController`
2. Criar `FormRequest` para validacao de entrada.
3. Regras minimas obrigatorias:
  - CPF valido;
  - telefone em formato internacional (E.164) para WhatsApp;
  - campos obrigatorios de contrato e colaborador;
  - unicidade de email e cpf.
4. Expor endpoints para:
  - cadastro/listagem de colaboradores;
  - criacao/listagem de contratos.

### Fase 4 - Geracao de PDF de contrato

1. Instalar `barryvdh/laravel-dompdf`.
2. Criar template HTML de contrato com variaveis dinamicas.
3. Gerar PDF ao criar contrato e salvar em `storage/app/contracts`.
4. Persistir caminho em `contracts.file_path`.

### Fase 5 - Integracao com Autentique (diferencial backend)

1. Criar `app/Services/AutentiqueService.php`.
2. Encapsular chamada GraphQL multipart com `Http::withToken()->attach()`.
3. Implementar envio com mutation de criacao de documento.
4. Incluir `security_verifications` exigindo biometria:
  - `"type": "LIVE"`.
5. Garantir tratamento de erros HTTP e de payload da Autentique.

### Fase 6 - Filas + rate limiting (60 req/min)

1. Criar job `SendContractToAutentique`.
2. Mover envio para a Autentique para dentro do job.
3. Configurar fila com Redis.
4. Aplicar `RateLimiter` no `handle()`:
  - se exceder limite, usar `release(delay)` para reprocessar depois;
  - evitar resposta `429` da API externa.
5. Atualizar `contracts.status` para refletir ciclo (`DRAFT`, `PENDING`, `SIGNED`, `REJECTED`).

### Fase 7 - Webhooks resilientes

1. Criar rota `POST /api/webhooks/autentique` em `routes/api.php`.
2. Controller do webhook deve:
  - validar assinatura do payload (se aplicavel);
  - gravar payload bruto em `webhook_logs`;
  - retornar `200` rapidamente (meta: < 200ms);
  - despachar `ProcessWebhookJob`.
3. `ProcessWebhookJob` deve:
  - identificar tipo de evento (ex: `document.signed`);
  - mapear status para contrato;
  - atualizar contrato por `autentique_document_id`;
  - marcar log como processado ou salvar `error_message`.

### Fase 8 - Frontend Angular

1. Criar services HTTP para consumir `http://localhost:8000/api`.
2. Tela Dashboard:
  - tabela com contratos e status;
  - atualizacao por polling (intervalo em segundos) ou SSE/WebSocket.
3. Tela Novo Contrato:
  - formulario de colaborador e dados de envio;
  - acao de gerar/enviar contrato;
  - feedback de sucesso/erro.
4. Tratar estados de carregamento, erro e vazio.

### Fase 9 - Qualidade, testes e documentacao

1. Criar testes de fluxo (Feature Tests), incluindo `ContractFlowTest`.
2. Mockar integracao externa com `Http::fake()`.
3. Cobrir cenarios minimos:
  - criacao de colaborador e contrato;
  - enfileiramento de envio;
  - processamento de webhook;
  - transicao correta de status.
4. Documentar API com Swagger (L5-Swagger) ou colecao Postman.
5. Atualizar README com:
  - arquitetura;
  - como subir ambiente;
  - decisoes tecnicas principais.

## 5) Definition of Done por fase

- Fase 1 pronta quando API e frontend sobem localmente e variaveis de ambiente estao configuradas.
- Fase 2 pronta quando migrations aplicam sem erro e constraints/indices estao ativos.
- Fase 3 pronta quando endpoints validam corretamente e retornam erros padronizados.
- Fase 4 pronta quando PDF e gerado e salvo com caminho persistido no contrato.
- Fase 5 pronta quando service envia documento para Autentique com biometria `LIVE`.
- Fase 6 pronta quando envio roda por job com rate limit e retentativa.
- Fase 7 pronta quando webhook responde rapido e processamento ocorre em background.
- Fase 8 pronta quando dashboard reflete status e formulario cria/envia contrato.
- Fase 9 pronta quando testes passam e documentacao de API/README esta publicada.

## 6) Riscos e pontos de atencao

1. Excesso de chamadas para Autentique sem fila/rate limit (quebra de fluxo por `429`).
2. Webhook lento por processamento sincrono no controller.
3. Falta de idempotencia ao processar webhook repetido.
4. Inconsistencia de status entre base local e provedor externo.
5. Validacao fraca de dados sensiveis (CPF/telefone/email).
6. Falta de observabilidade (logs, erros e rastreabilidade do contrato).

## 7) Acoes que voce deve tomar fora do codigo

1. Criar e validar conta Sandbox na Autentique.
2. Gerar e guardar token de API em local seguro (nao compartilhar em chats/prints).
3. Definir com RH/negocio o texto oficial do contrato e regras de assinatura.
4. Confirmar regras juridicas:
  - exigencia de biometria;
  - validade de assinatura;
  - requisitos de retencao de documentos.
5. Definir SLA operacional:
  - tempo maximo para envio;
  - tempo esperado para atualizacao de status;
  - estrategia de reprocessamento.
6. Definir politicas de seguranca:
  - acesso a ambientes;
  - rotacao de segredos;
  - backup e retencao.
7. Preparar material para portfolio/recrutadores:
  - contexto do problema;
  - decisoes arquiteturais;
  - resultados e aprendizados.
8. Planejar homologacao com casos reais simulados (assinatura, rejeicao, erro de webhook).

## 8) Perguntas em aberto sobre o projeto

1. Qual sera a regra oficial de transicao de status quando houver eventos fora da ordem?
R: Use a que você recomenda
2. Precisamos suportar multiplos signatarios por contrato agora ou em fase futura?
R: Se possível trabalharemos com múltiplos, mas deixe isso como um TODO, no momento trabalharemos com apenas 1
3. O envio por WhatsApp depende de integracao adicional alem da Autentique?
R: Pesquise e entenda qual a melhor maneira de fazer isso que seja gratuito
4. Qual estrategia de idempotencia sera adotada para webhooks duplicados?
R: Utilize a mais recomendada segundo as normas de desenvolvimento
5. Qual tempo de polling no dashboard e aceitavel para negocio?
R: O menor possível
6. Havera autenticacao/autorizacao no frontend e na API ja nesta primeira entrega?
R: Sim, deveremos ter um login padrão para a pessoa que controla o dashboard, quem irá assinar não precisa de login, apenas preenche as informações necessárias
7. Qual padrao de logs e monitoramento sera usado (arquivo, stack externa, ambos)?
R: Implemente algum tipo de servidor de logs que fica monitorando e armazenando em tempo real
8. Qual volume esperado de contratos por minuto para calibrar filas e workers?
R: A API da Autentique limita a 60 requisições/minuto. O envio de contratos será empurrado para o Redis (SendContractJob). Se o limite for atingido, o Laravel usará o RateLimiter para atrasar a execução da fila (delay/release), evitando erros HTTP 429.
9. Qual o criterio para retentativa maxima de jobs antes de marcar falha definitiva?
R: defina o padrão utilizado normalmente
10. Qual ferramenta de documentacao sera padrao final (Swagger ou Postman)?
R: Swagger, implemente-o também
11. Existe requisito de LGPD especifico para mascaramento/anonimizacao de dados exibidos?
R: Não iremos focar nisso no momento

