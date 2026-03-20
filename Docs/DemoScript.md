# Roteiro para demo gravada (1–2 min)

Use ambiente com **API + Redis + worker + Autentique** configurados (`AUTENTIQUE_API_TOKEN`, webhook apontando para sua URL pública ou túnel).

1. **Abrir dashboard** — listar contratos vazios ou existentes.
2. **Criar colaborador** (se necessário) e **criar contrato** — mostrar que o status fica em fila / pendente.
3. **Worker** — `queue:work` processando; mostrar link **Abrir Autentique** na linha do contrato.
4. **Assinar** no fluxo da Autentique (sandbox ou documento de teste).
5. **Webhook** — confirmar recebimento (logs ou fila `webhooks`) e linha no dashboard como **SIGNED**.

**Dica:** grave em 1080p, sem áudio ou com narração curta; destaque a transição **PENDING → SIGNED** após o evento.
