<?php

return [

    'api_rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),

    'webhook_rate_limit_per_minute' => (int) env('WEBHOOK_RATE_LIMIT_PER_MINUTE', 300),

    'metrics_token' => env('METRICS_TOKEN'),

];
