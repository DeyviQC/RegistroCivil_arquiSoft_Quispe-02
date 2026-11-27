<?php

return [
    // Secret for load tests: requests with the X-Prueba-Carga header set to this value skip
    // the attempt limits. Leave empty except while running k6. Ignored in production.
    'carga_token' => env('PRUEBAS_CARGA_TOKEN'),
];
