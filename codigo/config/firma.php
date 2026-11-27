<?php

return [
    'python' => env('FIRMA_PYTHON', 'python'),
    'peru_credenciales' => env('FIRMAPERU_CREDENTIALS_FILE') ?: storage_path('app/private/firma/fwAuthorization.json'),
    'peru_puerto' => (int) env('FIRMAPERU_PORT', 48596),
    // Days a signed copy of an act can be delivered again without signing (0 = no limit).
    // Some institutions only accept certified copies issued within the last 90 days.
    'vigencia_copia_dias' => (int) env('FIRMA_VIGENCIA_COPIA_DIAS', 90),
];
