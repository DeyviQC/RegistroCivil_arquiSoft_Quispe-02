<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon|null $fecha_nacimiento
 * @property string|null $madre_dni_estado
 * @property string|null $padre_dni_estado
 * @property string|null $madre_dni
 * @property string|null $padre_dni
 * @property int|null $filiacion_version
 */
class ActaNacimiento extends Model
{
    use HasFactory;

    protected $table = 'acta_nacimientos';

    protected $fillable = ['acta_id', 'apellido_nombre', 'dni', 'dni_no_consignado', 'fecha_nacimiento', 'observaciones'];

    public const CAMPOS_FILIACION = ['madre_nombre', 'madre_nombre_estado', 'madre_dni', 'madre_dni_estado', 'padre_nombre', 'padre_nombre_estado', 'padre_dni', 'padre_dni_estado'];
    public const ESTADOS_FILIACION = ['PENDIENTE' => 'Pendiente de revisar', 'REGISTRADO' => 'Registrado', 'NO_CONSIGNADO' => 'No consignado en el acta', 'DESCONOCIDO' => 'No se conoce (según el acta)', 'ILEGIBLE' => 'No legible en el documento'];

    public function __construct(array $attributes = [])
    {
        $this->fillable = array_merge($this->fillable, self::CAMPOS_FILIACION);
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date', 'dni_no_consignado' => 'boolean', 'filiacion_version' => 'integer'];
    }

    public function acta(): BelongsTo
    {
        return $this->belongsTo(Acta::class);
    }
}
