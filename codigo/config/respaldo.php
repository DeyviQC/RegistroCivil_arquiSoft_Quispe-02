<?php

return [
    // Carpeta destino. En producción debe estar en OTRO disco (o una unidad
    // de red/externa): un respaldo en el mismo disco no protege de su falla.
    'ruta' => env('RESPALDO_RUTA') ?: storage_path('app/respaldos'),

    // Días que se conservan los volcados de la base de datos.
    'dias_base_datos' => (int) env('RESPALDO_DIAS_BD', 14),

    // Ejecutable de mysqldump (en Herd/DBngin puede requerir la ruta completa).
    'mysqldump' => env('RESPALDO_MYSQLDUMP', 'mysqldump'),

    // Frase con la que se cifra la copia de APP_KEY (app-key.enc). Guárdala FUERA
    // de este equipo (gestor de contraseñas o papel): sin APP_KEY los datos
    // cifrados de los ciudadanos del respaldo no se pueden leer.
    'frase' => env('RESPALDO_FRASE'),
];
