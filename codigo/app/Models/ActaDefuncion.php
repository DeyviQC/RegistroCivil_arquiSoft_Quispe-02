<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActaDefuncion extends Model
{
    use HasFactory;

    protected $table = 'acta_defunciones';

    protected $fillable = ['acta_id', 'apellido_nombre', 'dni', 'dni_no_consignado', 'edad', 'fecha_defuncion', 'causa_defuncion'];

    protected function casts(): array
    {
        return ['fecha_defuncion' => 'date', 'dni_no_consignado' => 'boolean'];
    }

    public function acta(): BelongsTo
    {
        return $this->belongsTo(Acta::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return (string) $this->apellido_nombre;
    }
}
