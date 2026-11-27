<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActaResource;
use App\Models\Acta;
use App\Services\ActaService;
use Illuminate\Http\Request;

/**
 * API REST interna (Sección 49). Requiere autenticación por sesión
 * (middleware auth) o Sanctum; no es una API pública.
 */
class ActaApiController extends Controller
{
    public function __construct(private readonly ActaService $actaService)
    {
    }

    public function index()
    {
        $this->authorize('viewAny', Acta::class);

        return ActaResource::collection(
            Acta::with('usuario')->latest('fecha_acta')->paginate(20)
        );
    }

    public function show(Acta $acta)
    {
        $this->authorize('view', $acta);

        return new ActaResource($acta->load(['usuario', 'nacimiento', 'matrimonio', 'defuncion']));
    }

    public function search(Request $request)
    {
        $this->authorize('viewAny', Acta::class);

        $filtros = $request->only(['numero_acta', 'anio', 'tipo', 'dni', 'nombre', 'apellido', 'fecha_desde', 'fecha_hasta']);

        return ActaResource::collection($this->actaService->buscar($filtros));
    }
}
