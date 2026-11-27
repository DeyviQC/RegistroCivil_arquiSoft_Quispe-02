<?php

namespace App\Services;

use App\Models\Acta;
use App\Models\Auditoria;

class DashboardService
{
    public function estadisticas(): array
    {
        return $this->resumen()['estadisticas'];
    }

    public function actasPorAnio(): array
    {
        return $this->resumen()['por_anio'];
    }

    /** Five aggregate queries over the whole archive, served from cache until an act changes. */
    private function resumen(): array
    {
        return CacheConsultas::recordar(CacheConsultas::PANEL, fn () => [
            'estadisticas' => [
                'nacimientos' => Acta::where('tipo', Acta::TIPO_NACIMIENTO)->count(),
                'matrimonios' => Acta::where('tipo', Acta::TIPO_MATRIMONIO)->count(),
                'defunciones' => Acta::where('tipo', Acta::TIPO_DEFUNCION)->count(),
                'total' => Acta::count(),
            ],
            'por_anio' => Acta::selectRaw('anio, COUNT(*) as total')
                ->groupBy('anio')
                ->orderBy('anio', 'desc')
                ->limit(6)
                ->pluck('total', 'anio')
                ->toArray(),
        ]);
    }

    // Latest acts and activity change constantly and are cheap indexed reads: not cached.
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
