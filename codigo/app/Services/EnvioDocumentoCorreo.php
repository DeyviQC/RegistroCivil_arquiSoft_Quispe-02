<?php

namespace App\Services;

use App\Mail\DocumentoFirmadoMail;
use App\Models\TramitePublico;
use Illuminate\Support\Facades\{Cache, DB, Mail, Storage};

/** Sends the published signed PDF to the citizen's verified e-mail, at most once. */
class EnvioDocumentoCorreo
{
    public function __construct(private readonly AuditoriaService $audit) {}

    /** Whether the citizen asked for e-mail delivery and it has not been sent yet. */
    public function pendiente(TramitePublico $t): bool
    {
        return $t->estado === 'LISTA' && $t->email_verificado_at
            && ! empty($t->datos['canales_entrega']['correo'])
            && ! empty($t->datos['entrega']['revision'])
            && empty($t->datos['entrega']['correo_enviado_at']);
    }

    /**
     * @return bool true if sent now, false if not requested or already sent.
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface|\Illuminate\Contracts\Cache\LockTimeoutException
     */
    public function enviar(int $tramiteId, string $detalle): bool
    {
        // The cache lock serialises concurrent attempts, so the SMTP call happens
        // outside the database transaction without holding a row lock open.
        return Cache::lock('entrega-correo:'.$tramiteId, 120)->block(5, function () use ($tramiteId, $detalle) {
            $t = TramitePublico::findOrFail($tramiteId);
            abort_unless($t->pago?->estado === 'PAGADO', 403);
            if (! $this->pendiente($t)) return false;
            $entrega = $t->datos['entrega'];
            $disk = Storage::disk('local');
            $ruta = $entrega['firmado_ruta'] ?? '';
            abort_unless($ruta && $disk->exists($ruta) && hash_equals($entrega['firmado_sha256'] ?? '', hash_file('sha256', $disk->path($ruta))), 409);
            Mail::to($t->email)->send(new DocumentoFirmadoMail($t->id, $ruta));
            DB::transaction(function () use ($t, $detalle) {
                $actual = TramitePublico::lockForUpdate()->findOrFail($t->id);
                $datos = $actual->datos;
                $datos['entrega']['correo_enviado_at'] = now()->toIso8601String();
                $actual->update(['datos' => $datos, 'version' => $actual->version + 1]);
                $this->audit->registrar('ENVIAR_DOCUMENTO_CORREO', 'Portal ciudadano', $actual->id, $detalle);
            });
            return true;
        });
    }
}
