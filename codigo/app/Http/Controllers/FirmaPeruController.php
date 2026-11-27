<?php

namespace App\Http\Controllers;

use App\Models\TramitePublico;
use App\Models\User;
use App\Services\{AuditoriaService, FirmaDocumentoService, FirmaPeruService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FirmaPeruController extends Controller
{
    private function privado($response)
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }

    private function url(string $route, array $params): string
    {
        return rtrim(config('app.url'), '/').route($route, $params, false);
    }

    public function iniciar(Request $request, TramitePublico $tramite, FirmaPeruService $firma, AuditoriaService $audit)
    {
        $request->validate(['version' => 'required|integer']);
        abort_unless($tramite->estado === 'APROBADA' && $tramite->pago?->estado === 'PAGADO', 403);
        if ($tramite->version !== $request->integer('version') || isset($tramite->datos['entrega']['firmado_ruta'])) {
            throw ValidationException::withMessages(['firma_peru' => 'La solicitud cambió o ya tiene firma. Recarga antes de continuar.']);
        }
        $jwt = $firma->token();
        $result = DB::transaction(function () use ($request, $tramite, $jwt, $audit) {
            $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
            abort_unless($actual->estado === 'APROBADA' && $actual->pago?->estado === 'PAGADO' && $actual->version === $request->integer('version') && ! isset($actual->datos['entrega']['firmado_ruta']), 409);
            $disk = Storage::disk('local');
            abort_unless($actual->acta?->pdf_ruta && $disk->exists($actual->acta->pdf_ruta), 404);
            $attempt = (string) Str::uuid();
            $paramToken = Str::random(64);
            $documentToken = Str::random(64);
            $uploadToken = Str::random(64);
            $base = ['tramite' => $actual->id, 'intento' => $attempt];
            $ruta = 'private/entregas/'.$actual->id.'/'.$attempt.'-firmaperu-original.pdf';
            if (! $disk->put($ruta, $disk->get($actual->acta->pdf_ruta))) throw new \RuntimeException('No se pudo preparar el documento.');
            // Pass the archive copy unchanged: Firma Perú draws the real certificate appearance.
            $params = [
                'signatureFormat' => 'PAdES', 'signatureLevel' => 'B', 'signaturePackaging' => 'enveloped',
                'documentToSign' => $this->url('firmaperu.documento', $base + ['token' => $documentToken]),
                'certificateFilter' => '.*FIR.*', 'webTsa' => '', 'userTsa' => '', 'passwordTsa' => '',
                'theme' => 'claro', 'visiblePosition' => true, 'contactInfo' => 'solicitud-'.$actual->id,
                'signatureReason' => 'Atención de solicitud de copia de acta', 'bachtOperation' => false,
                'oneByOne' => true, 'signatureStyle' => 1,
                'imageToStamp' => $this->url('firmaperu.logo', $base + ['token' => $documentToken]),
                'stampTextSize' => 14, 'stampWordWrap' => 37, 'role' => '', 'stampPage' => 1,
                'positionx' => 20, 'positiony' => 20,
                'uploadDocumentSigned' => $this->url('firmaperu.resultado', $base + ['token' => $uploadToken]),
                'certificationSignature' => false, 'token' => $jwt,
            ];
            $entrega = ['preparado_ruta' => $ruta, 'preparado_sha256' => hash_file('sha256', $disk->path($ruta)),
                'preparado_por' => $request->user()->id, 'proveedor_firma' => 'FIRMA_PERU',
                'firma_peru' => ['intento' => $attempt, 'expira' => now()->addMinutes(10)->timestamp,
                    'usuario_id' => $request->user()->id, 'param_hash' => hash('sha256', $paramToken),
                    'documento_hash' => hash('sha256', $documentToken), 'resultado_hash' => hash('sha256', $uploadToken),
                    'parametros' => $params, 'parametros_usados' => false, 'estado' => 'INICIADA']];
            $actual->update(['datos' => array_merge($actual->datos, ['entrega' => $entrega]), 'version' => $actual->version + 1]);
            $audit->registrar('INICIAR_FIRMAPERU', 'Portal ciudadano', $actual->id, 'Inicio de firma desde la web.');
            return ['intento' => $attempt, 'port' => config('firma.peru_puerto'), 'param' => base64_encode(json_encode([
                'param_url' => $this->url('firmaperu.parametros', $base), 'param_token' => $paramToken, 'document_extension' => 'pdf'])),
                'estado_url' => route('firmaperu.estado', $actual)];
        });
        return $this->privado(response()->json($result));
    }

    private function intento(TramitePublico $tramite, string $id, string $token, string $purpose): array
    {
        $intento = $tramite->datos['entrega']['firma_peru'] ?? [];
        $hash = $intento[$purpose.'_hash'] ?? '';
        abort_unless($tramite->estado === 'APROBADA' && $tramite->pago?->estado === 'PAGADO'
            && ($intento['intento'] ?? '') === $id && ($intento['expira'] ?? 0) > now()->timestamp
            && ($intento['estado'] ?? '') === 'INICIADA' && strlen($token) === 64 && is_string($hash)
            && hash_equals($hash, hash('sha256', $token)), 403);
        $actor = User::find($intento['usuario_id'] ?? 0);
        abort_unless($actor && $actor->is_active && ($actor->hasRole('administrador') || $actor->hasRole('asistente')), 403);
        return $intento;
    }

    public function parametros(Request $request, TramitePublico $tramite, string $intento)
    {
        $encoded = DB::transaction(function () use ($request, $tramite, $intento) {
            $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
            $attempt = $this->intento($actual, $intento, (string) $request->input('param_token', ''), 'param');
            abort_if($attempt['parametros_usados'] ?? false, 403);
            $params = $attempt['parametros'];
            $attempt['parametros_usados'] = true;
            unset($attempt['parametros']);
            $datos = $actual->datos;
            $datos['entrega']['firma_peru'] = $attempt;
            $actual->update(['datos' => $datos]);
            return base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        });
        return $this->privado(response($encoded, 200, ['Content-Type' => 'text/plain; charset=UTF-8']));
    }

    public function documento(Request $request, TramitePublico $tramite, string $intento)
    {
        $this->intento($tramite, $intento, (string) $request->query('token', ''), 'documento');
        $entrega = $tramite->datos['entrega'];
        $disk = Storage::disk('local');
        abort_unless($disk->exists($entrega['preparado_ruta']) && hash_equals($entrega['preparado_sha256'], hash_file('sha256', $disk->path($entrega['preparado_ruta']))), 409);
        return $this->privado(response()->file($disk->path($entrega['preparado_ruta']), ['Content-Type' => 'application/pdf']));
    }

    public function logo(Request $request, TramitePublico $tramite, string $intento)
    {
        $this->intento($tramite, $intento, (string) $request->query('token', ''), 'documento');
        return $this->privado(response()->file(public_path('img/municipalidad/logo-firma.png'), ['Content-Type' => 'image/png']));
    }

    public function resultado(Request $request, TramitePublico $tramite, string $intento, FirmaDocumentoService $firma, AuditoriaService $audit)
    {
        // Authenticate the native client before parsing its uploaded file.
        $this->intento($tramite, $intento, (string) $request->query('token', ''), 'resultado');
        $request->validate(['signed_file' => 'required|file|mimes:pdf|max:20480']);
        $ruta = null;
        try {
            DB::transaction(function () use ($request, $tramite, $intento, $firma, $audit, &$ruta) {
                $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
                $attempt = $this->intento($actual, $intento, (string) $request->query('token', ''), 'resultado');
                abort_unless($attempt['parametros_usados'] ?? false, 403);
                $entrega = $actual->datos['entrega'];
                abort_if(isset($entrega['firmado_ruta']), 409);
                $disk = Storage::disk('local');
                $prepared = $disk->get($entrega['preparado_ruta']);
                abort_unless(hash_equals($entrega['preparado_sha256'], hash('sha256', $prepared)), 409);
                $pdf = $request->file('signed_file')->get();
                $verified = $firma->verificar($pdf, $prepared);
                $ruta = 'private/entregas/'.$actual->id.'/'.Str::uuid().'-firmaperu-firmado.pdf';
                if (! $disk->put($ruta, $pdf)) throw new \RuntimeException('No se pudo guardar el documento firmado.');
                $attempt['estado'] = 'RECIBIDA';
                unset($attempt['parametros'], $attempt['param_hash'], $attempt['documento_hash'], $attempt['resultado_hash']);
                $entrega = array_merge($entrega, $verified, ['firmado_ruta' => $ruta, 'firmado_sha256' => hash('sha256', $pdf),
                    'cargado_por' => $attempt['usuario_id'], 'cargado_at' => now()->toIso8601String(), 'firma_peru' => $attempt]);
                $actual->update(['datos' => array_merge($actual->datos, ['entrega' => $entrega]), 'version' => $actual->version + 1]);
                $audit->registrar('RECIBIR_FIRMAPERU', 'Portal ciudadano', $actual->id, 'Firma recibida e integridad verificada; pendiente de revisión.', $attempt['usuario_id']);
            });
        } catch (\Throwable $e) {
            if ($ruta) Storage::disk('local')->delete($ruta);
            throw $e;
        }
        return $this->privado(response()->json(['ok' => true]));
    }

    public function estado(TramitePublico $tramite)
    {
        $entrega = $tramite->datos['entrega'] ?? [];
        return $this->privado(response()->json(['recibida' => isset($entrega['firmado_ruta']) && ($entrega['integridad'] ?? '') === 'VERIFICADA',
            'expirada' => ($entrega['firma_peru']['expira'] ?? 0) <= now()->timestamp]));
    }

    public function cancelar(Request $request, TramitePublico $tramite)
    {
        $request->validate(['intento' => 'required|uuid']);
        DB::transaction(function () use ($request, $tramite) {
            $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
            $datos = $actual->datos;
            $attempt = $datos['entrega']['firma_peru'] ?? [];
            abort_unless(($attempt['intento'] ?? '') === $request->input('intento') && ($attempt['usuario_id'] ?? null) === $request->user()->id, 403);
            if (($attempt['estado'] ?? '') !== 'INICIADA') return;
            $attempt['estado'] = 'CANCELADA';
            unset($attempt['parametros'], $attempt['param_hash'], $attempt['documento_hash'], $attempt['resultado_hash']);
            $datos['entrega']['firma_peru'] = $attempt;
            $actual->update(['datos' => $datos]);
            app(AuditoriaService::class)->registrar('CANCELAR_FIRMAPERU', 'Portal ciudadano', $actual->id, 'Intento de firma cancelado por el personal.');
        });
        return response()->json(['ok' => true]);
    }
}
