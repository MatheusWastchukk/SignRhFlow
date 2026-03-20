<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'SignRhFlow API',
    version: '1.0.0',
    description: 'SignRhFlow orquestra RH (colaboradores, contratos, PDF), envia documentos a Autentique via fila e consome webhooks idempotentes. Autentique e a fonte de verdade da assinatura; o app mantem filas, links e status no dashboard.'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Servidor local'
)]
#[OA\SecurityScheme(
    securityScheme: 'metricsBearer',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'METRICS_TOKEN',
    description: 'Token definido em METRICS_TOKEN no .env (habilita GET /api/metrics).'
)]
#[OA\Tag(name: 'Employees', description: 'Gestao de colaboradores')]
#[OA\Tag(name: 'Contracts', description: 'Gestao de contratos')]
#[OA\Tag(name: 'Webhooks', description: 'Eventos de integracao externa')]
#[OA\Tag(name: 'Signing', description: 'Fluxo publico de assinatura por token')]
#[OA\Tag(name: 'Health', description: 'Disponibilidade e readiness')]
#[OA\Tag(name: 'Operations', description: 'Observabilidade e metricas')]
class OpenApiSpec {}
