<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */

    'api_rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),

    'webhook_rate_limit_per_minute' => (int) env('WEBHOOK_RATE_LIMIT_PER_MINUTE', 300),

    /*
    |--------------------------------------------------------------------------
    | Métricas (GET /api/metrics)
    |--------------------------------------------------------------------------
    |
    | Defina METRICS_TOKEN e envie Authorization: Bearer <token>.
    | Sem token configurado, a rota não é registrada (404).
    |
    */

    'metrics_token' => env('METRICS_TOKEN'),

];
