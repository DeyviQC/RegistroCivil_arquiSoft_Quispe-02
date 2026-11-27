<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ActaResource;
use App\Models\Acta;
use App\Services\ActaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Search and detail of the municipal archive for staff (same service as the web panel). */
class ActaController extends Controller
{
    public function __construct(private readonly ActaService $actas)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Acta::class);
        $filtros = $request->validate([
            'tipo' => ['nullable', Rule::in(['NACIMIENTO', 'MATRIMONIO', 'DEFUNCION'])],
            'anio' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'numero_acta' => ['nullable', 'string', 'max:20'],
            'nombre' => ['nullable', 'string', 'min:2', 'max:100'],
            'dni' => ['nullable', 'regex:/^[0-9]{1,15}$/'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return ActaResource::collection($this->actas->buscar($filtros));
    }

    public function show(Acta $acta): ActaResource
    {
        $this->authorize('view', $acta);

        return new ActaResource($acta->load(['usuario', 'nacimiento', 'matrimonio', 'defuncion']));
    }
}
