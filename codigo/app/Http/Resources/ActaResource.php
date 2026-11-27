<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Acta */
class ActaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'numero_acta' => $this->numero_acta,
            'orden_tipo' => $this->orden_tipo,
            'anio' => $this->anio,
            'tipo' => $this->tipo,
            'fecha_acta' => $this->fecha_acta?->format('Y-m-d'),
            'estado' => $this->estado,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
