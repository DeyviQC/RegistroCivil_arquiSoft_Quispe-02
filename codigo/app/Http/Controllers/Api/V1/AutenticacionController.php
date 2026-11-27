<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Bearer tokens (Laravel Sanctum) for staff using the REST API.
 * Same rules as the web login: active staff only, 5 failed attempts lock the
 * username for a minute, every login and failure is audited; tokens expire.
 */
class AutenticacionController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
    }

    public function emitir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'dispositivo' => ['nullable', 'string', 'max:60'],
        ]);
        $clave = 'api-login|'.Str::lower($datos['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            return response()->json(['message' => 'Demasiados intentos fallidos. Intente nuevamente en '.RateLimiter::availableIn($clave).' segundos.'], 429);
        }

        $usuario = User::where('username', $datos['username'])->first();
        if (! $usuario || ! Hash::check($datos['password'], $usuario->password)) {
            RateLimiter::hit($clave, 60);
            $this->auditoria->registrar(AuditoriaService::LOGIN_FALLIDO, 'API REST', null, "Intento fallido de token para '{$datos['username']}'.");

            return response()->json(['message' => 'Las credenciales ingresadas no son válidas.'], 401);
        }
        if (! $usuario->is_active || (! $usuario->isAdmin() && ! $usuario->isAsistente())) {
            return response()->json(['message' => 'Esta cuenta no tiene acceso a la API del personal.'], 403);
        }

        RateLimiter::clear($clave);
        $expira = now()->addMinutes((int) config('sanctum.expiration', 480));
        $token = $usuario->createToken('api:'.($datos['dispositivo'] ?? 'cliente'), ['*'], $expira);
        $this->auditoria->registrar(AuditoriaService::LOGIN, 'API REST', $usuario->id, 'Token emitido para la API REST.', $usuario->id);

        return response()->json([
            'token' => $token->plainTextToken,
            'tipo' => 'Bearer',
            'expira_en' => $expira->toIso8601String(),
            'usuario' => $this->usuario($usuario),
        ], 201);
    }

    public function yo(Request $request): JsonResponse
    {
        return response()->json(['usuario' => $this->usuario($request->user())]);
    }

    public function revocar(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();
        $this->auditoria->registrar(AuditoriaService::LOGOUT, 'API REST', $request->user()->id, 'Token revocado.');

        return response()->noContent();
    }

    private function usuario(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'username' => $usuario->username,
            'rol' => $usuario->isAdmin() ? 'administrador' : 'asistente',
        ];
    }
}
