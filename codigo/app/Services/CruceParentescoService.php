<?php

namespace App\Services;

use App\Models\Acta;

class CruceParentescoService
{
    public function comparar(Acta $acta, array $datos): array
    {
        $base = ['evaluado_at' => now()->toIso8601String(), 'relacion' => $datos['relacion'] ?? null, 'filiacion_version' => null];
        if (!in_array($datos['relacion'] ?? null, ['padre', 'madre'], true)) return $base + ['resultado' => 'NO_APLICA'];
        if ($acta->tipo !== 'NACIMIENTO' || !$acta->nacimiento) return $base + ['resultado' => 'SIN_DATOS'];
        $detalle = $acta->nacimiento;
        $base['filiacion_version'] = $detalle->filiacion_version;
        $campo = $datos['relacion'].'_dni';
        if ($detalle->{$campo.'_estado'} !== 'REGISTRADO' || !$detalle->$campo) return $base + ['resultado' => 'SIN_DATOS'];
        if (!preg_match('/^[0-9]{8}$/', (string) ($datos['dni'] ?? ''))) return $base + ['resultado' => 'SIN_DATOS'];
        return $base + ['resultado' => hash_equals($detalle->$campo, $datos['dni']) ? 'COINCIDE' : 'NO_COINCIDE'];
    }
}
