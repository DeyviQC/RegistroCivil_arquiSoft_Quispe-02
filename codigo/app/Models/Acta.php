<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Archived civil-registry act (original scanned PDF plus its detail record).
 *
 * @property \Illuminate\Support\Carbon $fecha_acta
 * @property string|null $firmado_ruta
 * @property string|null $firmado_sha256
 * @property string|null $firmado_original_hash
 * @property string|null $firmado_firmante
 * @property string|null $firmado_certificado_sha256
 * @property \Illuminate\Support\Carbon|null $firmado_at
 * @property int|null $firmado_por
 * @property int|null $firmado_tramite_id
 */
class Acta extends Model
{
    use HasFactory;

    public const TIPO_NACIMIENTO = 'NACIMIENTO';
    public const TIPO_MATRIMONIO = 'MATRIMONIO';
    public const TIPO_DEFUNCION = 'DEFUNCION';

    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_REGISTRADA = 'REGISTRADA';
    public const ESTADO_ANULADA = 'ANULADA';

    protected $table = 'actas';
    protected ?array $numeracionHistorica = null;

    public function asignarNumeracionHistorica(int $orden, int $sobre, int $correlativo): void
    {
        if ($orden < 1 || $sobre < 1 || $correlativo < 1 || $correlativo > 255) {
            throw new \InvalidArgumentException('Numeración histórica inválida.');
        }
        $this->numeracionHistorica = [$orden, (string) $sobre, $correlativo];
    }

    protected $fillable = [
        'numero_acta', 'anio', 'tipo', 'fecha_acta', 'sobre', 'correlativo',
        'observaciones', 'usuario_id', 'estado',
        'pdf_original', 'pdf_ruta', 'pdf_hash', 'pdf_tamano',
    ];

    protected function casts(): array
    {
        return [
            'fecha_acta' => 'date',
            'anio' => 'integer',
            'orden_tipo' => 'integer',
            'correlativo' => 'integer',
            'pdf_tamano' => 'integer',
            'firmado_at' => 'datetime',
        ];
    }

    /**
     * Whether the stored signed copy can be delivered again without a new signature:
     * the original is unchanged since it was signed, the file is intact, and it is
     * not older than the configured validity (0 = no limit).
     */
    public function copiaFirmadaVigente(): bool
    {
        if (! $this->firmado_ruta || ! $this->firmado_at || ! $this->pdf_hash
            || ! hash_equals((string) $this->firmado_original_hash, (string) $this->pdf_hash)) {
            return false;
        }
        $dias = (int) config('firma.vigencia_copia_dias', 90);
        if ($dias > 0 && $this->firmado_at->lt(now()->subDays($dias))) return false;
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        return $disk->exists($this->firmado_ruta)
            && hash_equals((string) $this->firmado_sha256, hash_file('sha256', $disk->path($this->firmado_ruta)));
    }

    public function copiaFirmadaVenceEl(): ?\Illuminate\Support\Carbon
    {
        $dias = (int) config('firma.vigencia_copia_dias', 90);
        return $this->firmado_at && $dias > 0 ? $this->firmado_at->copy()->addDays($dias) : null;
    }

    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $this->getConnection()->transaction(function () use ($query) {
            $secuencias = $this->getConnection()->table('acta_secuencias');
            $secuencia = (clone $secuencias)->where('tipo', $this->tipo)->lockForUpdate()->first();
            if (! $secuencia) {
                throw new \LogicException('Tipo de acta sin secuencia configurada.');
            }
            if ($this->numeracionHistorica !== null) {
                [$this->orden_tipo, $this->sobre, $this->correlativo] = $this->numeracionHistorica;
            } else {
                $ultimo = static::where('tipo', $this->tipo)->orderByDesc('orden_tipo')->first();
                $sobre = $ultimo ? (int) $ultimo->sobre : 1;
                $correlativo = $ultimo ? $ultimo->correlativo + 1 : 1;
                if ($correlativo > 25) { $sobre++; $correlativo = 1; }
                $this->orden_tipo = $secuencia->ultimo + 1;
                $this->sobre = (string) max(1, $sobre);
                $this->correlativo = $correlativo;
            }
            $resultado = parent::performInsert($query);
            if ($resultado) {
                (clone $secuencias)->where('tipo', $this->tipo)->update(['ultimo' => max($secuencia->ultimo, $this->orden_tipo)]);
            }

            return $resultado;
        });
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return HasOne<ActaNacimiento, $this> */
    public function nacimiento(): HasOne
    {
        return $this->hasOne(ActaNacimiento::class);
    }

    /** @return HasOne<ActaMatrimonio, $this> */
    public function matrimonio(): HasOne
    {
        return $this->hasOne(ActaMatrimonio::class);
    }

    /** @return HasOne<ActaDefuncion, $this> */
    public function defuncion(): HasOne
    {
        return $this->hasOne(ActaDefuncion::class);
    }

    public function detalle(): ActaNacimiento|ActaMatrimonio|ActaDefuncion|null
    {
        return match ($this->tipo) {
            self::TIPO_NACIMIENTO => $this->nacimiento,
            self::TIPO_MATRIMONIO => $this->matrimonio,
            self::TIPO_DEFUNCION => $this->defuncion,
        };
    }

    public function getCodigoAttribute(): string
    {
        return 'ACT-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function estaBloqueada(): bool
    {
        return $this->estado === self::ESTADO_REGISTRADA;
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }
}
