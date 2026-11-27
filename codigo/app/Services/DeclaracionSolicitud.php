<?php

namespace App\Services;

class DeclaracionSolicitud
{
    public const VERSION = '2026-10-13-v1';
    public static function textos(): array
    {
        $final = ' Declaro que los datos proporcionados son verdaderos y acepto la revisión municipal y la solicitud de sustento adicional. El pago no garantiza la entrega del documento.';
        return [
            'titular' => 'Declaro que soy el titular del acta solicitada.'.$final,
            'madre' => 'Declaro que soy la madre del titular del acta solicitada.'.$final,
            'padre' => 'Declaro que soy el padre del titular del acta solicitada.'.$final,
            'representante' => 'Declaro que actúo en representación del titular y que puedo presentar el sustento correspondiente.'.$final,
            'otro' => 'Declaro que la relación y el motivo indicados describen mi solicitud; corresponde a la municipalidad evaluar si procede su atención.'.$final,
        ];
    }
}
