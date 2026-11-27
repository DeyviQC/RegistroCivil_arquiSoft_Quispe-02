<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    use HasFactory;

    public const ACCIONES = [
        'LOGIN' => 'Inicio de sesión',
        'LOGOUT' => 'Cierre de sesión',
        'LOGIN_FALLIDO' => 'Intento de ingreso fallido',
        'CREAR_ACTA' => 'Registro de acta',
        'CONSULTAR_ACTA' => 'Consulta de acta',
        'VER_PDF' => 'Consulta del documento PDF',
        'IMPRIMIR_PDF' => 'Solicitud de impresión',
        'SUBIR_PDF' => 'Archivo PDF adjuntado',
        'INTENTO_DUPLICADO' => 'Intento de registrar un acta repetida',
        'CREAR_USUARIO' => 'Creación de usuario',
        'EDITAR_USUARIO' => 'Actualización de usuario',
        'DESACTIVAR_USUARIO' => 'Cambio de estado del usuario',
        'ACTIVAR_USUARIO' => 'Activación de usuario',
        'RESTABLECER_CLAVE' => 'Restablecimiento de contraseña',
        'IMPORTACION_MASIVA' => 'Carga de registros desde archivos',
        'OPTIMIZAR_PDF' => 'Reducción del tamaño de documentos PDF',
    ];

    public function getAccionLegibleAttribute(): string
    {
        return self::ACCIONES[$this->accion] ?? ucfirst(mb_strtolower(str_replace('_', ' ', $this->accion)));
    }

    public static function nombreModulo(string $modulo): string
    {
        return $modulo === 'Autenticación' ? 'Acceso al sistema' : $modulo;
    }

    public const UPDATED_AT = null; // solo interesa created_at

    protected $table = 'auditoria';

    protected $fillable = [
        'usuario_id',
        'accion',
        'modulo',
        'registro_id',
        'descripcion',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
