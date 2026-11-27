<?php

namespace App\Services;

use App\Contracts\ContrastadorIdentidad;
use App\Support\NombrePersona;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Adapter for the APIsPerú DNI endpoint behind the ContrastadorIdentidad contract. */
class ApisPeruService implements ContrastadorIdentidad
{
    public function contrastar(array $datos): string
    {
        if (!config('portal.apisperu_token')) return self::PENDIENTE_CONFIGURACION;
        // The account panel supports Bearer authentication; keep tokens out of URLs and logs.
        try {
            $respuesta = Http::timeout(10)->connectTimeout(5)->withOptions(PortalHttpOptions::trusted())->withToken(config('portal.apisperu_token'))->acceptJson()->get('https://dniruc.apisperu.com/api/v1/dni/'.$datos['dni']);
        } catch (\Throwable $e) { throw new RuntimeException('Consulta DNI temporalmente no disponible.'); }
        if (!$respuesta->successful()) throw new RuntimeException('Consulta DNI temporalmente no disponible.');
        $persona = $respuesta->json();
        if (is_array($persona) && ($persona['success'] ?? null) === false && ($persona['message'] ?? '') === 'No se encontraron resultados.') return self::SIN_RESULTADOS;
        if (!is_array($persona) || ($persona['dni'] ?? '') !== $datos['dni']) throw new RuntimeException('No se pudieron contrastar los datos del DNI.');
        $nombre = trim(($persona['nombres'] ?? '').' '.($persona['apellidoPaterno'] ?? '').' '.($persona['apellidoMaterno'] ?? ''));
        if (! NombrePersona::coinciden($nombre, $datos['nombre'])) return self::NO_COINCIDE;
        if (isset($persona['codVerifica']) && (string) $persona['codVerifica'] !== $datos['verificador']) return self::NO_COINCIDE;
        // Birth date and ubigeo are not in the documented DNI schema. Never mark them verified.
        return self::DATOS_CONTRASTADOS;
    }
}
