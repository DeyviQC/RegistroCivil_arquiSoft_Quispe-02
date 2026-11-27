<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function index()
    {
        return view('dashboard.index', [
            'estadisticas' => $this->dashboardService->estadisticas(),
            'actasPorAnio' => $this->dashboardService->actasPorAnio(),
            'ultimasActas' => $this->dashboardService->ultimasActas(),
            'actividadReciente' => $this->dashboardService->actividadReciente(),
        ]);
    }
}
