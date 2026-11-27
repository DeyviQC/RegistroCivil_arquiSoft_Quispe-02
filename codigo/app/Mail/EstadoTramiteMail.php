<?php

namespace App\Mail;

use App\Models\TramitePublico;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

/**
 * Aviso al ciudadano cuando su solicitud cambia de estado.
 * Va en cola: la respuesta del personal no espera al servidor de correo
 * y, si el envío falla, se reintenta sin repetir el cambio de estado.
 */
class EstadoTramiteMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Segundos entre reintentos: 1 min, 5 min, 15 min, 1 h. */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $solicitud, public string $estado, public string $mensaje)
    {
        // Only send once the state change has been committed to the database.
        $this->afterCommit();
    }

    public function build(): self
    {
        $etiqueta = TramitePublico::ESTADOS[$this->estado] ?? $this->estado;

        return $this->subject('Solicitud N.º '.$this->numero().' · '.$etiqueta)
            ->view('emails.estado-tramite', [
                'numero' => $this->numero(),
                'etiqueta' => $etiqueta,
                'indicacion' => self::INDICACIONES[$this->estado] ?? null,
                'enlace' => self::enlaceDirecto(TramitePublico::find($this->solicitud)),
            ]);
    }

    /**
     * Signed link that opens this request's tracking without asking for a new code.
     * It only goes to the verified mailbox, which is what the code proves anyway.
     */
    public static function enlaceDirecto(?TramitePublico $tramite): ?string
    {
        if (! $tramite?->email_verificado_at) return null;
        // Sign only the path: the link keeps working whether the site is opened by
        // its domain or by IP (e.g. a laptop reached through 127.0.0.1 or a tunnel).
        return url(\Illuminate\Support\Facades\URL::temporarySignedRoute('portal.acceso', now()->addDays(self::DIAS_ENLACE), ['codigo' => $tramite->codigo], false));
    }

    public const DIAS_ENLACE = 7;

    private function numero(): string
    {
        return str_pad((string) $this->solicitud, 6, '0', STR_PAD_LEFT);
    }

    /** Estados que se notifican y qué debe hacer el ciudadano en cada uno. */
    public const INDICACIONES = [
        'PENDIENTE' => 'Recibimos tu pago. El personal de Registro Civil revisará tu solicitud.',
        'OBSERVADA' => 'Tu solicitud tiene una observación. Ingresa a tu seguimiento con tu DNI y correo y responde desde allí; puedes adjuntar un documento.',
        'APROBADA' => 'Tu solicitud fue aprobada. Te avisaremos cuando el documento firmado esté disponible.',
        'LISTA' => 'Tu documento firmado ya está disponible. Ingresa al seguimiento con tu DNI y correo para descargarlo.',
        'RECHAZADA' => 'Tu solicitud fue rechazada. Revisa el motivo y comunícate con Registro Civil si tienes dudas.',
    ];
}
