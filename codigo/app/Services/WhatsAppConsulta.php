<?php

namespace App\Services;

use App\Models\TramitePublico;

class WhatsAppConsulta
{
    public function enlace(TramitePublico $tramite): ?string
    {
        if ($tramite->pago?->estado !== 'PAGADO') return null;
        $configurado = config('portal.whatsapp_contacto');
        if (!is_string($configurado) || !preg_match('/^\+?[\d\s()-]+$/D', $configurado)) return null;
        $numero = preg_replace('/\D/', '', $configurado);
        if (!preg_match('/^[1-9]\d{7,14}$/D', $numero)) return null;
        $referencia = str_pad((string) $tramite->id, 6, '0', STR_PAD_LEFT);
        $mensaje = 'Hola, soy el solicitante de la solicitud N.º '.$referencia.' de Registro Civil. Ya realicé el pago y quiero recibir los avisos y mi documento por este WhatsApp.';
        return 'https://wa.me/'.$numero.'?text='.rawurlencode($mensaje);
    }
}
