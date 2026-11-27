<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoTramite extends Model
{
    protected $table = 'pagos_tramite';
    protected $guarded = ['id'];
    protected $hidden = ['qr', 'idempotencia', 'mercadopago_checkout_url'];
    protected function casts(): array { return ['importe_centimos' => 'integer', 'expira_at' => 'datetime', 'pagado_at' => 'datetime']; }
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<TramitePublico, $this> */
    public function tramite(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(TramitePublico::class, 'tramite_publico_id'); }
}
