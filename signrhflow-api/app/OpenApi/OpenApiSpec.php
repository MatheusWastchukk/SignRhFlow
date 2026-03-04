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
class OpenApiSpec
{
}
