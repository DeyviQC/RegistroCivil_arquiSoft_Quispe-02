<?php

return [
    'http_ca_file' => env('PORTAL_HTTP_CA_FILE'),
    'whatsapp_contacto' => env('PORTAL_WHATSAPP_CONTACTO'),
    'tarifa_centimos' => 1030,
    'terminos_version' => 'demo-2026-10-13-v1',
    // Días sin iniciar el pago antes de marcar la solicitud como VENCIDA.
    'dias_para_pagar' => (int) env('PORTAL_DIAS_PARA_PAGAR', 7),
    'apisperu_token' => env('APISPERU_TOKEN'),
    // Frequent reasons offered to staff when observing or rejecting (one click fills the text).
    'plantillas_observacion' => [
        'Falta DNI' => 'Adjunta una copia legible de tu DNI (ambas caras) respondiendo a esta observación.',
        'Acreditar vínculo' => 'Adjunta un documento que acredite tu vínculo con el titular del acta (por ejemplo, tu partida de nacimiento).',
        'Representación' => 'Como representante, adjunta la carta poder o el documento que te autoriza a solicitar el acta.',
        'Datos no coinciden' => 'Los datos que indicaste no coinciden con el acta. Revisa los nombres y la fecha y responde a esta observación.',
    ],
];
