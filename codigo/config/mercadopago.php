<?php

return [
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    'mode' => env('MERCADOPAGO_MODE', 'test'),
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    'collector_id' => env('MERCADOPAGO_COLLECTOR_ID'),
    'public_url' => env('MERCADOPAGO_PUBLIC_URL'),
];
