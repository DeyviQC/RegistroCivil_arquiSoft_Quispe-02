<?php

namespace App\Http\Resources\V1;

use App\Models\TramitePublico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Citizen request as exposed by the REST API (staff only).
 * Privacy by design: no access code, e-mail, DNI or uploaded files are returned.
 *
 * @mixin TramitePublico
 */
class TramiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $datos = $this->datos;

        return [
            'id' => $this->id,
            'numero' => str_pad((string) $this->id, 6, '0', STR_PAD_LEFT),
            'estado' => $this->estado,
            'estado_descripcion' => TramitePublico::ESTADOS[$this->estado] ?? $this->estado,
            // Send it back unchanged when deciding: it protects against concurrent decisions.
            'version' => (int) $this->version,
            'tipo_acta' => $datos['busqueda']['tipo'] ?? $this->acta?->tipo,
            'titular' => $datos['busqueda']['titular'] ?? null,
            'solicitante' => $datos['nombre'] ?? null,
            'relacion' => TramitePublico::RELACIONES[$datos['relacion'] ?? ''] ?? null,
            'pago' => $this->pago ? ['estado' => $this->pago->estado, 'importe' => round($this->pago->importe_centimos / 100, 2)] : null,
            'creado_en' => $this->created_at?->toIso8601String(),
            $this->mergeWhen($this->relationLoaded('eventos'), fn () => [
                'requiere_revision_vinculo' => $this->requiereRevisionVinculo(),
                'transiciones_permitidas' => TramitePublico::TRANSICIONES[$this->estado] ?? [],
                'respuestas_del_ciudadano' => count($datos['subsanaciones'] ?? []),
                'eventos' => $this->eventos->map(fn (\App\Models\TramiteEvento $e) => [
                    'estado' => $e->estado,
                    'mensaje' => $e->mensaje,
                    'fecha' => $e->created_at?->toIso8601String(),
                ])->values(),
            ]),
        ];
    }
}
