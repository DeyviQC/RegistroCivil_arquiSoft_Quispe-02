<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoriaService)
    {
    }

    public function mostrarLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $this->asegurarNoDemasiadosIntentos($request);

        $credenciales = $request->only('username', 'password');

        if (! Auth::attempt($credenciales, $request->boolean('remember'))) {
            \Illuminate\Support\Facades\RateLimiter::hit($this->claveLimitador($request), 60);

            $this->auditoriaService->registrar(
                AuditoriaService::LOGIN_FALLIDO,
                'Autenticación',
                null,
                "Intento fallido de inicio de sesión para usuario '{$request->input('username')}'."
            );

            throw ValidationException::withMessages([
                'username' => 'Las credenciales ingresadas no son válidas.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'El usuario se encuentra desactivado. Contacte al administrador.',
            ]);
        }

        // The panel is for municipal staff only; citizens use the portal without an account.
        if (! Auth::user()->isAdmin() && ! Auth::user()->isAsistente()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'Esta cuenta no tiene acceso al panel. Los trámites ciudadanos se realizan en '.route('portal.inicio').'.',
            ]);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($this->claveLimitador($request));
        $request->session()->regenerate();

        $this->auditoriaService->registrar(AuditoriaService::LOGIN, 'Autenticación');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auditoriaService->registrar(AuditoriaService::LOGOUT, 'Autenticación');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function asegurarNoDemasiadosIntentos(Request $request): void
    {
        $clave = $this->claveLimitador($request);

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($clave, 5)) {
            $segundos = \Illuminate\Support\Facades\RateLimiter::availableIn($clave);

            throw ValidationException::withMessages([
                'username' => "Demasiados intentos fallidos. Intente nuevamente en {$segundos} segundos.",
            ]);
        }
    }

    private function claveLimitador(Request $request): string
    {
        return \Illuminate\Support\Str::transliterate(
            strtolower($request->input('username', '')) . '|' . $request->ip()
        );
    }
}
