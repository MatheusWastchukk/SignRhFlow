<?php

return [

    'api_rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),

    'webhook_rate_limit_per_minute' => (int) env('WEBHOOK_RATE_LIMIT_PER_MINUTE', 300),

    'metrics_token' => env('METRICS_TOKEN'),

    /*
     * true = processa o webhook na mesma requisição (útil em dev sem depender do worker).
     * Em produção mantenha false e use o container queue + fila "webhooks".
     */
    'webhook_handle_sync' => filter_var(env('AUTENTIQUE_WEBHOOK_HANDLE_SYNC', false), FILTER_VALIDATE_BOOL),

];
