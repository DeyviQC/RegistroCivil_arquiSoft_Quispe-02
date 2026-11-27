<?php

namespace App\Services;

use App\Models\Acta;
use App\Models\Auditoria;

class DashboardService
{
    public function estadisticas(): array
    {
        return [
            'nacimientos' => Acta::where('tipo', Acta::TIPO_NACIMIENTO)->count(),
            'matrimonios' => Acta::where('tipo', Acta::TIPO_MATRIMONIO)->count(),
            'defunciones' => Acta::where('tipo', Acta::TIPO_DEFUNCION)->count(),
            'total' => Acta::count(),
        ];
    }

    public function actasPorAnio(): array
    {
        return Acta::selectRaw('anio, COUNT(*) as total')
            ->groupBy('anio')
            ->orderBy('anio', 'desc')
            ->limit(6)
            ->pluck('total', 'anio')
            ->toArray();
    }

    public function ultimasActas(int $cantidad = 8)
    {
        return Acta::with('usuario')
            ->latest('created_at')
            ->limit($cantidad)
            ->get();
    }

    public function actividadReciente(int $cantidad = 10)
    {
        return Auditoria::with('usuario')
            ->latest('created_at')
            ->limit($cantidad)
            ->get();
    }
}
