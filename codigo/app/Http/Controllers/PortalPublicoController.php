<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Models\TramitePublico;
use App\Services\BusquedaPublicaService;
use App\Services\CodigoTramiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PortalPublicoController extends Controller
{
    public function inicio() { return view('portal.buscar'); }

    public function buscar(Request $request, BusquedaPublicaService $busqueda)
    {
        $datos = $request->validate(['terminos' => ['accepted'], 'tipo' => ['required', Rule::in(['NACIMIENTO', 'MATRIMONIO', 'DEFUNCION'])], 'titular' => ['required', 'string', 'min:5', 'max:200'], 'fecha' => ['required', 'date', 'before_or_equal:today']]);
        $acta = $busqueda->buscar($datos);
        $request->session()->forget('portal_busqueda');
        if (!$acta) return back()->withInput()->with('aviso', 'No pudimos localizar una coincidencia única y disponible en el archivo digital. Consulta al Registro Civil para una búsqueda en el archivo físico. No se ha generado ningún cobro.');
        $request->session()->put('portal_busqueda', ['acta_id' => $acta->id, 'datos' => $datos, 'terminos_version' => config('portal.terminos_version'), 'aceptado_at' => now()->toIso8601String(), 'expira' => now()->addMinutes(30)->timestamp]);
        return redirect()->route('portal.datos');
    }

    public function datos(Request $request)
    {
        $busqueda = $this->busquedaActual($request);
        $acta = Acta::with('nacimiento')->findOrFail($busqueda['acta_id']);
        $autocompletar = [
            'nombre' => $busqueda['datos']['titular'],
            // A marriage/death event date is not the applicant's birth date.
            'nacimiento' => $acta->tipo === 'NACIMIENTO' ? $acta->nacimiento?->fecha_nacimiento?->format('Y-m-d') : null,
        ];
        return view('portal.datos', compact('busqueda', 'autocompletar'));
    }

    public function registrar(Request $request, CodigoTramiteService $codigos, \App\Services\ApisPeruService $dni)
    {
        $busqueda = $this->busquedaActual($request);
        $datos = $request->validate(['dni' => ['required', 'regex:/^[0-9]{8}$/'], 'nombre' => ['required', 'string', 'max:200'], 'nacimiento' => ['required', 'date', 'before_or_equal:today'], 'verificador' => ['required', 'regex:/^[0-9]$/'], 'ubigeo' => ['nullable', 'regex:/^[0-9]{6}$/'], 'email' => ['required', 'email', 'max:255'], 'whatsapp' => ['nullable', 'regex:/^\+[1-9][0-9]{7,14}$/'], 'declaracion' => ['accepted']]);
        $vinculo = $request->validate([
            'relacion' => ['required', Rule::in(array_keys(TramitePublico::RELACIONES))],
            'motivo' => ['required', 'string', 'max:1500'],
            'dni_titular' => ['nullable', 'regex:/^[0-9]{8}$/'],
        ]);
        $datos = array_merge($datos, $vinculo);
        try { $identidad = $dni->contrastar($datos); }
        catch (\RuntimeException $e) { throw ValidationException::withMessages(['dni' => 'La consulta DNI no está disponible. Inténtalo más tarde; no se ha generado ningún cobro.']); }
        if ($identidad === 'NO_COINCIDE') throw ValidationException::withMessages(['dni' => 'Los datos no coinciden con la consulta. Revisa DNI, nombres y dígito verificador.']);
        if ($identidad === 'SIN_RESULTADOS') throw ValidationException::withMessages(['dni' => 'El proveedor no devolvió resultados para los datos consultados. No se ha podido completar el contraste; no se ha generado una solicitud ni un cobro.']);
        $acta = Acta::findOrFail($busqueda['acta_id']);
        abort_unless($acta->estado === Acta::ESTADO_REGISTRADA && $acta->pdf_ruta && app(\App\Services\PdfStorageService::class)->existe($acta->pdf_ruta), 409);
        $datos['cruce_parentesco'] = app(\App\Services\CruceParentescoService::class)->comparar($acta, $datos);
        $datos['declaracion_detalle'] = [
            'texto' => \App\Services\DeclaracionSolicitud::textos()[$datos['relacion']],
            'version' => \App\Services\DeclaracionSolicitud::VERSION,
            'aceptada_at' => now()->toIso8601String(),
        ];
        $tramite = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => $acta->id, 'datos' => $datos + ['busqueda' => $busqueda['datos']], 'email' => strtolower($datos['email']), 'terminos_version' => $busqueda['terminos_version'], 'aceptado_at' => $busqueda['aceptado_at'], 'identidad_estado' => $identidad]);
        $request->session()->forget('portal_busqueda');
        $request->session()->forget(['portal_consulta_pendiente', 'portal_consultas']);
        $request->session()->put('portal_pendiente', $tramite->codigo);
        $request->session()->put('portal_codigo', $tramite->codigo);
        try { $codigos->enviar($tramite); }
        catch (\Throwable $e) { return redirect()->route('portal.codigo')->with('error', 'No se pudo enviar el código. Puedes solicitar un reenvío en un minuto.'); }
        return redirect()->route('portal.codigo');
    }

    public function codigo(Request $request)
    {
        abort_unless($request->session()->has('portal_pendiente'), 403);
        return view('portal.codigo');
    }

    public function reenviar(Request $request, CodigoTramiteService $codigos)
    {
        $tramite = TramitePublico::where('codigo', $request->session()->get('portal_pendiente'))->first();
        if (!$tramite) return back()->with('exito', 'Si los datos coinciden y el envío está disponible, recibirás un código de acceso.');
        try { $codigos->enviar($tramite); }
        catch (ValidationException $e) { throw $e; }
        catch (\Throwable $e) { return back()->with('error', 'No se pudo enviar el correo. Inténtalo más tarde.'); }
        return back()->with('exito', 'Código generado nuevamente.');
    }

    public function verificar(Request $request, CodigoTramiteService $codigos)
    {
        $datos = $request->validate(['codigo' => ['required', 'regex:/^[0-9]{6}$/']]);
        $tramite = TramitePublico::where('codigo', $request->session()->get('portal_pendiente'))->first();
        if (!$tramite || !$codigos->verificar($tramite, $datos['codigo'])) throw ValidationException::withMessages(['codigo' => 'Código incorrecto, vencido o sin intentos disponibles.']);
        $request->session()->regenerate();
        $consulta = $request->session()->pull('portal_consulta_pendiente');
        if ($consulta) {
            $request->session()->put('portal_consultas', ['ids' => $consulta, 'expira' => now()->addMinutes(30)->timestamp]);
            $request->session()->forget(['portal_pendiente', 'portal_acceso']);
            return redirect()->route('portal.solicitudes');
        }
        $request->session()->put('portal_acceso', ['codigo' => $tramite->codigo, 'expira' => now()->addMinutes(30)->timestamp]);
        $request->session()->forget('portal_pendiente');
        return redirect()->route('portal.estado');
    }

    public function estado(Request $request)
    {
        $tramite = $this->autorizado($request)->load('pago', 'eventos');
        return response()->view('portal.estado', compact('tramite'))->header('Cache-Control', 'private, no-store');
    }

    /** The citizen answers an observation; the request goes back to the staff inbox. */
    public function responder(Request $request)
    {
        $tramite = $this->autorizado($request);
        $datos = $request->validate([
            'respuesta' => ['required', 'string', 'min:10', 'max:1500'],
            'adjunto' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'respuesta.required' => 'Escribe tu respuesta a la observación.',
            'respuesta.min' => 'Tu respuesta debe tener al menos 10 caracteres.',
            'adjunto.mimes' => 'El documento debe ser PDF, JPG o PNG.',
            'adjunto.max' => 'El documento no debe superar los 5 MB.',
        ]);
        abort_unless($tramite->estado === 'OBSERVADA', 409, 'Esta solicitud no tiene observaciones por responder.');
        $ruta = $request->file('adjunto')?->store('private/subsanaciones/'.$tramite->id, 'local');
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($tramite, $datos, $ruta, $request) {
                $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
                abort_unless($actual->estado === 'OBSERVADA', 409, 'Esta solicitud no tiene observaciones por responder.');
                $subsanaciones = $actual->datos['subsanaciones'] ?? [];
                $subsanaciones[] = ['texto' => $datos['respuesta'], 'adjunto' => $ruta,
                    'nombre_adjunto' => $ruta ? Str::limit($request->file('adjunto')->getClientOriginalName(), 120, '') : null,
                    'enviado_at' => now()->toIso8601String()];
                $actual->datos = array_merge($actual->datos, ['subsanaciones' => $subsanaciones]);
                // The citizen wrote it: no "payment received" email back to them.
                $actual->transicionar('PENDIENTE', 'Respondiste la observación: '.$datos['respuesta'], notificar: false);
                app(\App\Services\AuditoriaService::class)->registrar('SUBSANAR_TRAMITE', 'Portal ciudadano', $actual->id, $ruta ? 'Respuesta con documento adjunto.' : 'Respuesta sin adjunto.');
            });
        } catch (\Throwable $e) {
            if ($ruta) \Illuminate\Support\Facades\Storage::disk('local')->delete($ruta);
            throw $e;
        }
        return redirect()->route('portal.estado')->with('exito', 'Enviamos tu respuesta. Registro Civil continuará con la revisión y te avisará por correo.');
    }

    /** Signed link from a notice e-mail: opens the request without a new code. */
    public function acceso(Request $request, string $codigo)
    {
        $tramite = $request->hasValidSignature(false) ?TramitePublico::where('codigo', $codigo)->whereNotNull('email_verificado_at')->first() : null;
        if (! $tramite) return redirect()->route('portal.seguimiento')->with('aviso', 'El enlace venció o no es válido. Ingresa con tu DNI y correo para recibir un código.');
        $request->session()->regenerate();
        $request->session()->forget(['portal_pendiente', 'portal_consultas']);
        $request->session()->put('portal_acceso', ['codigo' => $tramite->codigo, 'expira' => now()->addMinutes(30)->timestamp]);
        app(\App\Services\AuditoriaService::class)->registrar('ACCESO_ENLACE', 'Portal ciudadano', $tramite->id, 'Acceso con enlace firmado del correo.');
        return redirect()->route('portal.estado');
    }

    public function seguimiento() { return view('portal.seguimiento'); }

    public function recuperar(Request $request, CodigoTramiteService $codigos)
    {
        $datos = $request->validate(['dni' => ['required', 'regex:/^[0-9]{8}$/'], 'email' => ['required', 'email', 'max:255']]);
        // Personal details remain encrypted in storage; both columns are indexed and the
        // DNI is matched through its keyed hash (the applicant's, never the act holder's).
        $ids = TramitePublico::where('email', strtolower($datos['email']))
            ->where('dni_hash', TramitePublico::hashDni($datos['dni']))->orderByDesc('id')->pluck('id')->all();
        $tramite = $ids ? TramitePublico::find($ids[0]) : null;
        // Always show the same result, including for unknown references.
        $request->session()->forget(['portal_acceso', 'portal_consultas']);
        $request->session()->put('portal_consulta_pendiente', $ids);
        $request->session()->put('portal_pendiente', $tramite?->codigo ?? (string) Str::uuid());
        if ($tramite) {
            try { $codigos->enviar($tramite); } catch (\Throwable $e) { /* Generic response prevents enumeration. */ }
        }
        return redirect()->route('portal.codigo')->with('exito', 'Si los datos coinciden y el envío está disponible, recibirás un código de acceso.');
    }

    public function solicitudes(Request $request)
    {
        $acceso = $request->session()->get('portal_consultas');
        abort_unless($acceso && $acceso['expira'] > now()->timestamp, 403);
        $tramites = TramitePublico::whereIn('id', $acceso['ids'])->orderByDesc('id')->get();
        return response()->view('portal.mis-solicitudes', compact('tramites'))->header('Cache-Control', 'private, no-store');
    }

    public function seleccionar(Request $request)
    {
        $datos = $request->validate(['solicitud' => ['required', 'integer']]);
        $acceso = $request->session()->get('portal_consultas');
        abort_unless($acceso && $acceso['expira'] > now()->timestamp && in_array((int) $datos['solicitud'], $acceso['ids'], true), 403);
        $tramite = TramitePublico::findOrFail($datos['solicitud']);
        if (!$tramite->email_verificado_at) $tramite->update(['email_verificado_at' => now()]);
        $request->session()->put('portal_acceso', ['codigo' => $tramite->codigo, 'expira' => $acceso['expira']]);
        return redirect()->route('portal.estado');
    }

    public function documento(Request $request)
    {
        $tramite = $this->autorizado($request);
        $entrega = $tramite->datos['entrega'] ?? [];
        abort_unless($tramite->estado === 'LISTA' && $tramite->pago?->estado === 'PAGADO' && isset($entrega['revision'], $entrega['firmado_ruta'], $entrega['firmado_sha256']), 403);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($entrega['firmado_ruta']), 404);
        abort_unless(hash_equals($entrega['firmado_sha256'], hash_file('sha256', $disk->path($entrega['firmado_ruta']))), 409);
        app(\App\Services\AuditoriaService::class)->registrar('DESCARGAR_DOCUMENTO', 'Portal ciudadano', $tramite->id, 'Descarga con correo verificado.');
        return $disk->download($entrega['firmado_ruta'], 'mi-acta-firmada.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function autorizado(Request $request): TramitePublico
    {
        return \App\Http\Middleware\EnsurePortalAccess::tramite($request);
    }

    private function busquedaActual(Request $request): array
    {
        $busqueda = $request->session()->get('portal_busqueda');
        abort_unless($busqueda && $busqueda['expira'] > now()->timestamp, 403, 'Realiza nuevamente la búsqueda del acta.');
        return $busqueda;
    }
}
