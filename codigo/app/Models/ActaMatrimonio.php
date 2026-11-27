<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActaMatrimonio extends Model
{
    use HasFactory;

    protected $table = 'acta_matrimonios';

    protected $fillable = ['acta_id', 'apellido_nombre', 'dni', 'fecha_matrimonio', 'observaciones'];

    protected function casts(): array
    {
        return ['fecha_matrimonio' => 'date'];
    }

    public function acta(): BelongsTo
    {
        return $this->belongsTo(Acta::class);
    }

}
