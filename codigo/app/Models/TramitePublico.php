<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Citizen request from the public portal.
 *
 * @property int $id
 * @property string $codigo
 * @property int $acta_id
 * @property string $estado
 * @property int $version
 * @property array<string, mixed> $datos Encrypted at rest (encrypted:array).
 * @property string $email
 * @property string|null $dni_hash
 * @property string|null $identidad_estado
 * @property string|null $otp_hash
 * @property \Illuminate\Support\Carbon|null $email_verificado_at
 * @property \Illuminate\Support\Carbon|null $otp_expira_at
 * @property \Illuminate\Support\Carbon|null $otp_enviado_at
 * @property \Illuminate\Support\Carbon|null $aceptado_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Acta $acta
 * @property-read PagoTramite|null $pago
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TramiteEvento> $eventos
 */
class TramitePublico extends Model
{
    protected $table = 'tramites_publicos';
    protected $guarded = ['id'];
    protected $hidden = ['datos', 'email', 'otp_hash', 'dni_hash'];

    /** Keyed hash of a DNI (blind index): exact, indexed lookups without storing the DNI in clear text. */
    public static function hashDni(?string $dni): ?string
    {
        return $dni && preg_match('/^[0-9]{8}$/D', $dni) ? hash_hmac('sha256', 'dni:'.$dni, (string) config('app.key')) : null;
    }

    /** Changes made by the system or the citizen, not offered as staff decisions. */
    public const TRANSICIONES_AUTOMATICAS = [
        'PENDIENTE_PAGO' => ['PENDIENTE', 'VENCIDA'], // payment confirmed / never paid
        'OBSERVADA' => ['PENDIENTE'],                 // the citizen answered the observation
        'APROBADA' => ['LISTA'],                      // signed document published
    ];

    /**
     * The only way to change a request's state: checks the transition, bumps the
     * optimistic-lock version, saves any other pending changes and records the
     * history event (which e-mails the citizen unless $notificar is false).
     * Call it on a row locked with lockForUpdate() inside a transaction.
     */
    public function transicionar(string $estado, string $mensaje, ?int $usuarioId = null, bool $notificar = true): TramiteEvento
    {
        $permitidos = array_merge(self::TRANSICIONES[$this->estado] ?? [], self::TRANSICIONES_AUTOMATICAS[$this->estado] ?? []);
        if (! in_array($estado, $permitidos, true)) {
            throw new \DomainException("Transición no permitida: {$this->estado} → {$estado}.");
        }
        $this->estado = $estado;
        // A freshly created model may not carry the column default yet.
        $this->version = (int) ($this->version ?? static::whereKey($this->getKey())->value('version')) + 1;
        $this->save();
        $crear = fn () => $this->eventos()->create(['usuario_id' => $usuarioId, 'estado' => $estado, 'mensaje' => $mensaje]);
        return $notificar ? $crear() : TramiteEvento::withoutEvents($crear);
    }

    protected static function booted(): void
    {
        static::saving(function (TramitePublico $tramite) {
            if ($tramite->isDirty('datos')) $tramite->dni_hash = self::hashDni($tramite->datos['dni'] ?? null);
        });
    }
    public const RELACIONES = ['titular' => 'Soy el titular del acta', 'madre' => 'Soy la madre', 'padre' => 'Soy el padre', 'representante' => 'Soy representante', 'otro' => 'Otra relación'];

    public function requiereRevisionVinculo(): bool
    {
        // A declaration is never proof. Legacy requests with no relation also require review.
        if (($this->datos['relacion'] ?? null) !== 'titular') return true;
        $fecha = $this->acta?->tipo === 'NACIMIENTO' ? $this->acta->nacimiento?->fecha_nacimiento : null;
        return $this->acta?->tipo === 'NACIMIENTO' && (!$fecha || $fecha->gt(now()->subYears(18)));
    }
    public const ESTADOS = ['PENDIENTE_PAGO' => 'Pendiente de pago', 'PENDIENTE' => 'Pendiente de atención', 'EN_REVISION' => 'En revisión', 'OBSERVADA' => 'Observada', 'APROBADA' => 'Aprobada, pendiente de firma', 'LISTA' => 'Documento listo para descargar', 'RECHAZADA' => 'Rechazada', 'VENCIDA' => 'Vencida (no se realizó el pago)'];
    // Staff decide straight from the inbox; EN_REVISION is kept only for older requests.
    public const TRANSICIONES = ['PENDIENTE' => ['OBSERVADA', 'APROBADA', 'RECHAZADA'], 'EN_REVISION' => ['OBSERVADA', 'APROBADA', 'RECHAZADA'], 'OBSERVADA' => ['APROBADA', 'RECHAZADA'], 'APROBADA' => [], 'RECHAZADA' => [], 'VENCIDA' => []];
    protected function casts(): array
    {
        return ['datos' => 'encrypted:array', 'aceptado_at' => 'datetime', 'otp_expira_at' => 'datetime', 'otp_enviado_at' => 'datetime', 'email_verificado_at' => 'datetime'];
    }
    /** @return BelongsTo<Acta, $this> */
    public function acta(): BelongsTo { return $this->belongsTo(Acta::class); }
    /** @return HasOne<PagoTramite, $this> */
    public function pago(): HasOne { return $this->hasOne(PagoTramite::class); }
    /** @return HasMany<TramiteEvento, $this> */
    public function eventos(): HasMany { return $this->hasMany(TramiteEvento::class)->orderBy('id'); }
}
