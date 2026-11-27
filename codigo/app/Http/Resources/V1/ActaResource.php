<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Act of the municipal archive as exposed by the REST API (staff only).
 * Never includes the PDF itself or its storage path.
 *
 * @mixin \App\Models\Acta
 */
class ActaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detalle = $this->detalle();

        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'numero_acta' => $this->numero_acta,
            'anio' => $this->anio,
            'fecha_acta' => $this->fecha_acta?->format('Y-m-d'),
            'estado' => $this->estado,
            'titular' => $detalle?->apellido_nombre,
            'ubicacion' => ['sobre' => $this->sobre, 'correlativo' => $this->correlativo, 'orden_tipo' => $this->orden_tipo],
            'pdf_disponible' => (bool) $this->pdf_ruta,
            'registrado_en' => $this->created_at?->toIso8601String(),
            // Only on the detail endpoint: checking the signed copy reads the file.
            $this->mergeWhen($request->routeIs('api.v1.actas.show'), fn () => [
                'copia_firmada' => [
                    'vigente' => $this->copiaFirmadaVigente(),
                    'firmada_en' => $this->firmado_at?->toIso8601String(),
                    'vence_en' => $this->copiaFirmadaVenceEl()?->toIso8601String(),
                ],
                'registrado_por' => $this->usuario?->name,
            ]),
        ];
    }
}
