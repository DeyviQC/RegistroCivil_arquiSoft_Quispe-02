<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditoriaService
{
    public const LOGIN = 'LOGIN';
    public const LOGOUT = 'LOGOUT';
    public const CREAR_ACTA = 'CREAR_ACTA';
    public const CONSULTAR_ACTA = 'CONSULTAR_ACTA';
    public const VER_PDF = 'VER_PDF';
    public const IMPRIMIR_PDF = 'IMPRIMIR_PDF';
    public const SUBIR_PDF = 'SUBIR_PDF';
    public const INTENTO_DUPLICADO = 'INTENTO_DUPLICADO';
    public const CREAR_USUARIO = 'CREAR_USUARIO';
    public const EDITAR_USUARIO = 'EDITAR_USUARIO';
    public const DESACTIVAR_USUARIO = 'DESACTIVAR_USUARIO';
    public const IMPORTACION_MASIVA = 'IMPORTACION_MASIVA';
    public const LOGIN_FALLIDO = 'LOGIN_FALLIDO';

    public function registrar(
        string $accion,
        string $modulo,
        ?int $registroId = null,
        ?string $descripcion = null,
        ?int $usuarioId = null
    ): Auditoria {
        /** @var Request $request */
        $request = request();

        return Auditoria::create([
            'usuario_id' => $usuarioId ?? Auth::id(),
            'accion' => $accion,
            'modulo' => $modulo,
            'registro_id' => $registroId,
            'descripcion' => $descripcion,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
        ]);
    }
}
