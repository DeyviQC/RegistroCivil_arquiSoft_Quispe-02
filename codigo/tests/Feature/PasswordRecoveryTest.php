<?php
namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->seed(\Database\Seeders\RoleSeeder::class); }

    private function administrador(bool $activo=true): User { $user=User::factory()->create(['is_active'=>$activo]); $user->roles()->attach(\App\Models\Role::where('slug','administrador')->firstOrFail()); return $user; }

    public function test_asistente_no_recibe_correo_ni_puede_usar_un_token(): void
    {
        Notification::fake();
        $user=User::factory()->create(['is_active'=>true]);
        $user->roles()->attach(\App\Models\Role::where('slug','asistente')->firstOrFail());
        $original=$user->password;
        $this->post('/recuperar-contrasena',['email'=>$user->email])->assertSessionHas('status');
        Notification::assertNothingSent();
        $token=Password::createToken($user);
        $this->post('/restablecer-contrasena',['email'=>$user->email,'token'=>$token,'password'=>'NuevaClave123','password_confirmation'=>'NuevaClave123'])->assertSessionHasErrors('email');
        $this->assertSame($original,$user->fresh()->password);
    }

    public function test_token_deja_de_funcionar_si_la_cuenta_pierde_el_rol_administrador(): void
    {
        $user=$this->administrador();
        $token=Password::createToken($user);
        $original=$user->password;
        $user->roles()->detach();
        $this->post('/restablecer-contrasena',['email'=>$user->email,'token'=>$token,'password'=>'NuevaClave123','password_confirmation'=>'NuevaClave123'])->assertSessionHasErrors('email');
        $this->assertSame($original,$user->fresh()->password);
    }

    public function test_recuperacion_completa_y_token_de_un_solo_uso(): void
    {
        Notification::fake();
        $user=$this->administrador();
        $this->get('/login')->assertOk()->assertSee('¿Olvidaste tu contraseña?');
        $this->get('/recuperar-contrasena')->assertOk();
        $this->post('/recuperar-contrasena',['email'=>$user->email])->assertSessionHasNoErrors()->assertSessionHas('status');
        $token=null;
        Notification::assertSentTo($user,RestablecerContrasena::class,function($notification)use(&$token){$token=$notification->token;return true;});
        $this->assertNotNull($token);
        $this->get(route('password.reset',['token'=>$token,'email'=>$user->email]))->assertOk()->assertSee($user->email);
        $datos=['email'=>$user->email,'token'=>$token,'password'=>'NuevaClave123','password_confirmation'=>'NuevaClave123'];
        $this->post('/restablecer-contrasena',$datos)->assertRedirect('/login')->assertSessionHas('status');
        $this->assertTrue(Hash::check('NuevaClave123',$user->fresh()->password));
        $this->post('/restablecer-contrasena',$datos)->assertSessionHasErrors('email');
    }

    public function test_cuentas_inexistentes_o_desactivadas_no_reciben_enlaces(): void
    {
        Notification::fake();
        $user=$this->administrador(false);
        $this->post('/recuperar-contrasena',['email'=>$user->email])->assertSessionHas('status');
        $this->post('/recuperar-contrasena',['email'=>'inexistente@example.test'])->assertSessionHas('status');
        Notification::assertNothingSent();
        $token=Password::createToken($user);
        $this->post('/restablecer-contrasena',['email'=>$user->email,'token'=>$token,'password'=>'NuevaClave123','password_confirmation'=>'NuevaClave123'])->assertSessionHasErrors('email');
    }

    public function test_rechaza_token_vencido_y_confirmacion_incorrecta(): void
    {
        $user=$this->administrador();
        $old=$user->password;
        $token=Password::createToken($user);
        DB::table('password_reset_tokens')->where('email',$user->email)->update(['created_at'=>now()->subMinutes(61)]);
        $this->post('/restablecer-contrasena',['email'=>$user->email,'token'=>$token,'password'=>'NuevaClave123','password_confirmation'=>'NuevaClave123'])->assertSessionHasErrors('email');
        $this->post('/restablecer-contrasena',['email'=>$user->email,'token'=>$token,'password'=>'NuevaClave123','password_confirmation'=>'OtraClave123'])->assertSessionHasErrors('password');
        $this->assertSame($old,$user->fresh()->password);
    }
}
