<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otro usuario del personal cambió el registro después de abrirlo (bloqueo optimista).
 * En el panel web se muestra como error del formulario; en la API responde 409 Conflict.
 */
class ConflictoVersionException extends RuntimeException
{
    public function __construct(string $mensaje = 'La solicitud cambió. Recarga antes de decidir.')
    {
        parent::__construct($mensaje);
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'errors' => ['version' => [$this->getMessage()]]], 409);
        }

        return back()->withInput()->withErrors(['version' => $this->getMessage()]);
    }
}