<?php

namespace App\Models;

use App\Mail\EstadoTramiteMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * History entry of a citizen request (state change and its message).
 *
 * @property int $id
 * @property int $tramite_publico_id
 * @property int|null $usuario_id
 * @property string $estado
 * @property string|null $mensaje
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class TramiteEvento extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['created_at' => 'datetime']; }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<TramitePublico, $this> */
    public function tramite(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(TramitePublico::class, 'tramite_publico_id'); }

    protected static function booted(): void
    {
        // Every state change (staff review, payment confirmation, publication) creates
        // an event, so notifying here covers all of them in one place.
        static::created(function (TramiteEvento $evento) {
            if (! array_key_exists($evento->estado, EstadoTramiteMail::INDICACIONES)) {
                return;
            }
            $tramite = $evento->tramite;
            if (! $tramite?->email || ! $tramite->email_verificado_at) {
                return;
            }
            Mail::to($tramite->email)->queue(new EstadoTramiteMail($tramite->id, $evento->estado, (string) $evento->mensaje));
        });
    }
}
