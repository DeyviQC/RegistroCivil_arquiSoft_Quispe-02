<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecidirTramiteRequest;
use App\Models\TramitePublico;
use App\Services\CacheConsultas;
use App\Services\DecisionTramiteService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AtencionPublicaController extends Controller
{
    public function atender(DecidirTramiteRequest $request, TramitePublico $tramite, DecisionTramiteService $decision)
    {
        $decision->decidir($tramite, $request->validated(), $request->user());

        return back()->with('exito', $request->validated('estado') === 'APROBADA'
            ? 'Solicitud aprobada. Continúe con la firma del documento.'
            : 'Respuesta enviada al ciudadano.');
    }

    public function index(Request $request)
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::in(array_keys(TramitePublico::ESTADOS))], 'solicitud' => 'nullable|integer|min:1|max:2147483647', 'dni' => ['nullable', 'regex:/^[0-9]{8}$/']],
            ['dni.regex' => 'El DNI debe tener 8 dígitos.']);
        $base = TramitePublico::whereHas('pago', fn ($q) => $q->where('estado', 'PAGADO'));
        // Counting every paid request costs ~100 ms with 10 000 of them and no index removes it (Sprint 2, BD y caché).
        $resumen = collect(CacheConsultas::recordar(CacheConsultas::BANDEJA,
            fn () => (clone $base)->selectRaw('estado, COUNT(*) AS total')->groupBy('estado')->pluck('total', 'estado')->all()));
        $tramites = (clone $base)
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['solicitud'] ?? null, fn ($q, $numero) => $q->where('id', $numero))
            ->when($filtros['dni'] ?? null, fn ($q, $dni) => $q->where('dni_hash', TramitePublico::hashDni($dni)))
            ->latest('id')->paginate(15)->withQueryString();
        return view('portal.bandeja', compact('tramites', 'resumen', 'filtros'));
    }
    public function show(TramitePublico $tramite)
    {
        $tramite->load('acta', 'pago', 'eventos');
        $cruceActual = app(\App\Services\CruceParentescoService::class)->comparar($tramite->acta, $tramite->datos);
        return view('portal.revision', compact('tramite', 'cruceActual'));
    }
    /** Document attached by the citizen when answering an observation. */
    public function subsanacion(TramitePublico $tramite, int $indice)
    {
        $ruta = $tramite->datos['subsanaciones'][$indice]['adjunto'] ?? null;
        abort_unless(is_string($ruta) && str_starts_with($ruta, 'private/subsanaciones/'.$tramite->id.'/'), 404);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($ruta), 404);
        $tipo = $disk->mimeType($ruta);
        abort_unless(in_array($tipo, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);
        return $disk->response($ruta, 'sustento-solicitud-'.$tramite->id.'-'.($indice + 1).'.'.pathinfo($ruta, PATHINFO_EXTENSION), [
            'Content-Type' => $tipo, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
