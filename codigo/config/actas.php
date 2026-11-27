<?php

return [
    'pdf_max_kb' => (int) env('ACTAS_PDF_MAX_KB', 10240),

    // Datos fijos que aparecen en el documento generado. Cambian rara vez,
    // por eso van aquí y no como campo del formulario.
    'oficina_registral' => env('ACTAS_OFICINA_REGISTRAL', 'AYACUCHO / HUAMANGA / ANDRÉS AVELINO CÁCERES DORREGARAY (05 01 16 000)'),
    'registrador_nombre' => env('ACTAS_REGISTRADOR_NOMBRE', 'FERNANDO PIMENTEL GUTIÉRREZ'),
    'registrador_dni' => env('ACTAS_REGISTRADOR_DNI', '28251151'),
    'registrador_cargo' => env('ACTAS_REGISTRADOR_CARGO', 'ESPECIALISTA DE REGISTRO CIVIL'),
];