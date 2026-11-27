<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecidirTramiteRequest;
use App\Http\Resources\V1\TramiteResource;
use App\Models\TramitePublico;
use App\Services\DecisionTramiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Staff inbox of paid citizen requests and their decision, through the shared service. */
class TramiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(array_keys(TramitePublico::ESTADOS))],
            'dni' => ['nullable', 'regex:/^[0-9]{8}$/'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], ['dni.regex' => 'El DNI debe tener 8 dígitos.']);

        $tramites = TramitePublico::with(['pago', 'acta'])
            ->whereHas('pago', fn ($q) => $q->where('estado', 'PAGADO'))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['dni'] ?? null, fn ($q, $dni) => $q->where('dni_hash', TramitePublico::hashDni($dni)))
            ->latest('id')->paginate(15)->withQueryString();

        return TramiteResource::collection($tramites);
    }

    public function show(TramitePublico $tramite): TramiteResource
    {
        abort_unless($tramite->pago?->estado === 'PAGADO', 404);

        return new TramiteResource($tramite->load(['pago', 'acta', 'eventos']));
    }

    public function decidir(DecidirTramiteRequest $request, TramitePublico $tramite, DecisionTramiteService $decision): JsonResponse
    {
        $actualizado = $decision->decidir($tramite, $request->validated(), $request->user());

        return response()->json([
            'message' => $request->validated('estado') === 'APROBADA'
                ? 'Solicitud aprobada. Continúe con la firma del documento.'
                : 'Respuesta enviada al ciudadano.',
            'data' => new TramiteResource($actualizado->fresh(['pago', 'acta', 'eventos'])),
        ]);
    }
}
