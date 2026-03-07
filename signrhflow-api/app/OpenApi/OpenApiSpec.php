<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'SignRhFlow API',
    version: '1.0.0',
    description: 'Documentacao inicial dos endpoints de colaboradores e contratos.'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Servidor local'
)]
#[OA\Tag(name: 'Employees', description: 'Gestao de colaboradores')]
#[OA\Tag(name: 'Contracts', description: 'Gestao de contratos')]
#[OA\Tag(name: 'Webhooks', description: 'Eventos de integracao externa')]
class OpenApiSpec
{
}
