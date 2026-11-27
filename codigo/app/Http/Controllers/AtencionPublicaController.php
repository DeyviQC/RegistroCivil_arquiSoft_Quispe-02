<?php

namespace App\Http\Controllers;

use App\Models\TramitePublico;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AtencionPublicaController extends Controller
{
    /** Default message shown to the citizen when the staff member does not write one. */
    private const MENSAJES = [
        'EN_REVISION' => 'La municipalidad está revisando tu solicitud.',
        'APROBADA' => 'Tu solicitud fue aprobada. Estamos preparando tu acta firmada.',
    ];

    public function index(Request $request)
    {
        $filtros = $request->validate(['estado' => ['nullable', Rule::in(array_keys(TramitePublico::ESTADOS))], 'solicitud' => 'nullable|integer|min:1|max:2147483647', 'dni' => ['nullable', 'regex:/^[0-9]{8}$/']],
            ['dni.regex' => 'El DNI debe tener 8 dígitos.']);
        $base = TramitePublico::whereHas('pago', fn ($q) => $q->where('estado', 'PAGADO'));
        $resumen = (clone $base)->selectRaw('estado, COUNT(*) AS total')->groupBy('estado')->pluck('total', 'estado');
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

    public function atender(Request $request, TramitePublico $tramite, AuditoriaService $auditoria)
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in(array_keys(TramitePublico::ESTADOS))],
            // Only an observation or rejection needs a reason written by the staff member.
            'mensaje' => ['nullable', 'required_if:estado,OBSERVADA,RECHAZADA', 'string', 'max:2000'],
            'version' => ['required', 'integer'],
        ], ['mensaje.required_if' => 'Escribe el motivo para que el ciudadano sepa qué hacer.']);
        $datos['mensaje'] = trim((string) ($datos['mensaje'] ?? '')) ?: (self::MENSAJES[$datos['estado']] ?? 'Tu solicitud fue actualizada.');
        DB::transaction(function () use ($tramite, $datos, $request, $auditoria) {
            $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
            if ($actual->version !== (int) $datos['version']) throw ValidationException::withMessages(['version' => 'La solicitud cambió. Recarga antes de decidir.']);
            if ($actual->pago?->estado !== 'PAGADO' || !in_array($datos['estado'], TramitePublico::TRANSICIONES[$actual->estado] ?? [], true)) throw ValidationException::withMessages(['estado' => 'Cambio de estado no permitido.']);
            if ($datos['estado'] === 'APROBADA' && $actual->requiereRevisionVinculo()) {
                $revision = $request->validate([
                    'vinculo_revisado' => ['accepted'],
                    'sustento_revision' => ['required', 'string', 'min:10', 'max:1500'],
                ]);
                $actual->datos = array_merge($actual->datos, ['revision_vinculo' => [
                    'usuario_id' => $request->user()->id,
                    'revisado_at' => now()->toIso8601String(),
                    'sustento' => $revision['sustento_revision'],
                    'cruce_parentesco' => app(\App\Services\CruceParentescoService::class)->comparar($actual->acta, $actual->datos),
                ]]);
            }
            $actual->transicionar($datos['estado'], $datos['mensaje'], $request->user()->id);
            $auditoria->registrar('REVISAR_TRAMITE', 'Portal ciudadano', $actual->id, 'Estado: '.$datos['estado']);
        });
        return back()->with('exito', $datos['estado'] === 'APROBADA'
            ? 'Solicitud aprobada. Continúe con la firma del documento.'
            : 'Respuesta enviada al ciudadano.');
    }
}
