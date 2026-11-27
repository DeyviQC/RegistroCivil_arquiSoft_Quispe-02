<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function solicitar() { return view('auth.password-recovery'); }

    public function enviar(Request $request)
    {
        $datos = $request->validate(['email'=>['required','email','max:255']]);
        if (app()->environment('local') && (in_array(config('mail.default'), ['log','array'])
            || (config('mail.mailers.smtp.host') === 'smtp.gmail.com' && empty(config('mail.mailers.smtp.password'))))) {
            return back()->withErrors(['email'=>'Todavía no se pueden enviar correos. Configure la contraseña de aplicación de Gmail y active MAIL_MAILER=smtp. No se envió ningún enlace.'])->withInput();
        }
        if (app()->environment('local') && config('mail.mailers.smtp.host') === 'smtp.gmail.com'
            && strlen(preg_replace('/\s+/', '', (string) config('mail.mailers.smtp.password'))) !== 16) {
            return back()->withErrors(['email'=>'La configuración de Gmail necesita una contraseña de aplicación de 16 caracteres. La clave actual no tiene ese formato. No se envió el enlace.'])->withInput();
        }
        if ($this->administradorActivo($datos['email'])) {
            try {
                $resultado = Password::sendResetLink($datos);
                if ($resultado === Password::RESET_THROTTLED) {
                    return back()->with('status','Si ya solicitó un enlace, revise su correo o espere un minuto antes de volver a solicitarlo.')->withInput();
                }
            } catch (\Throwable $e) {
                $usuario = User::where('email',$datos['email'])->first();
                if ($usuario) { Password::deleteToken($usuario); }
                report($e);
                return back()->withErrors(['email'=>'No se pudo procesar la solicitud. Inténtelo más tarde o contacte al administrador.'])->withInput();
            }
        }
        return back()->with('status','Si el correo corresponde a un administrador activo, recibirá el enlace de recuperación.');
    }

    public function formulario(Request $request, string $token)
    {
        return view('auth.password-recovery', ['token'=>$token,'email'=>$request->query('email','')]);
    }

    public function actualizar(Request $request)
    {
        $datos = $request->validate([
            'token'=>['required','string','max:255'], 'email'=>['required','email','max:255'],
            'password'=>['required','confirmed',PasswordRule::min(8)->letters()->numbers()],
        ], [
            'password.required'=>'Escriba su nueva contraseña.',
            'password.confirmed'=>'Las contraseñas no coinciden. Escriba la misma en ambos campos.',
            'password_confirmation.required'=>'Confirme su nueva contraseña.',
        ]);
        if (!$this->administradorActivo($datos['email'])) {
            return back()->withErrors(['email'=>'El enlace no es válido o ha vencido. Solicite uno nuevo.'])->withInput($request->only('email'));
        }
        $estado = Password::reset($datos, function (User $user, string $password) {
            if (!$user->is_active || !$user->isAdmin()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['email'=>'La recuperación solo está disponible para administradores activos.']);
            }
            $user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60)])->save();
            // Invalidate existing database sessions after password recovery.
            if (config('session.driver') === 'database') {
                \Illuminate\Support\Facades\DB::connection(config('session.connection'))
                    ->table(config('session.table','sessions'))->where('user_id',$user->id)->delete();
            }
            event(new PasswordReset($user));
            app(\App\Services\AuditoriaService::class)->registrar('RESTABLECER_CLAVE','Usuarios',$user->id,
                'Contraseña restablecida mediante enlace de recuperación.', $user->id);
        });
        if ($estado !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email'=>'El enlace no es válido o ha vencido. Solicite uno nuevo.'])->withInput($request->only('email'));
        }
        return redirect()->route('login')->with('status','Contraseña actualizada. Inicie sesión con su usuario y la nueva contraseña.');
    }

    private function administradorActivo(string $email): bool
    {
        return User::where('email',$email)->where('is_active',true)
            ->whereHas('roles',fn($q)=>$q->where('slug','administrador'))->exists();
    }
}
