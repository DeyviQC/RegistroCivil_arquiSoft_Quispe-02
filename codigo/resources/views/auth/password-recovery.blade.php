<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recuperar contraseña | Registro Civil</title><link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet"><link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet"><link href="{{ asset('css/municipal-sistema.css') }}?v={{ filemtime(public_path('css/municipal-sistema.css')) }}" rel="stylesheet"></head>
<body class="municipal-system login-bg d-flex align-items-center justify-content-center min-vh-100"><main class="card shadow login-card"><div class="card-body p-4 p-sm-5">
<div class="text-center mb-4"><img src="{{ asset('img/logo.jpg') }}" alt="Escudo municipal" width="70"><h4 class="mt-3">{{ isset($token)?'Nueva contraseña':'Recuperar contraseña' }}</h4></div>
@if(!isset($token))<p>Recuperación exclusiva para administradores. Ingrese el correo registrado en su cuenta; el enlace vence en 60 minutos.</p><p class="small text-muted">Después de solicitarlo, abra el enlace desde su correo. Revise también Spam. Si es asistente, solicite el cambio de contraseña al administrador.</p>@endif
@if(app()->environment('local') && in_array(config('mail.default'),['log','array']))<div class="alert alert-warning">El envío de correo aún no está configurado. Contacte al administrador para habilitar la recuperación por correo.</div>@endif
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<form method="POST" action="{{ route(isset($token)?'password.update':'password.email') }}">@csrf
@if(isset($token))<input type="hidden" name="token" value="{{ $token }}">@endif
<label class="form-label" for="email">Correo electrónico</label><input id="email" type="email" name="email" maxlength="255" autocomplete="email" value="{{ old('email',$email??'') }}" class="form-control mb-3" required autofocus>
@if(isset($token))
<div class="alert alert-info small">Esta será la contraseña para entrar al sistema de Registro Civil, no la de Gmail. Debe tener al menos 8 caracteres, una letra y un número. Escríbala igual en ambos campos.</div>
@foreach(['password'=>'Nueva contraseña','confirmation'=>'Confirmar nueva contraseña'] as $id=>$etiqueta)
<label class="form-label" for="{{ $id }}">{{ $etiqueta }}</label>
<div class="password-field mb-2">
    <input id="{{ $id }}" type="password" name="{{ $id==='password'?'password':'password_confirmation' }}" minlength="8" autocomplete="new-password" class="form-control" required aria-describedby="{{ $id==='password'?'reglasClave':'coincidenciaClave' }}">

</div>
@if($id==='password')
<ul id="reglasClave" class="small text-muted mb-3 ps-3"><li data-regla="longitud">Al menos 8 caracteres</li><li data-regla="letra">Al menos una letra</li><li data-regla="numero">Al menos un número</li></ul>
@else
<p id="coincidenciaClave" class="small text-muted mb-3" aria-live="polite">Repita exactamente la nueva contraseña.</p>
@endif
@endforeach
@endif
<button class="btn btn-primary w-100">{{ isset($token)?'Guardar nueva contraseña':'Enviar enlace de recuperación' }}</button></form>
<div class="text-center mt-3"><a href="{{ route('login') }}">Volver al inicio de sesión</a>@if(isset($token))<br><a href="{{ route('password.request') }}">Solicitar otro enlace</a>@endif</div>
</div></main>
@if(isset($token))
<script>
const nuevaClave=document.getElementById('password');
const confirmacion=document.getElementById('confirmation');
function validarClave(){
    const reglas={longitud:nuevaClave.value.length>=8,letra:/\p{L}/u.test(nuevaClave.value),numero:/\p{N}/u.test(nuevaClave.value)};
    Object.entries(reglas).forEach(([nombre,cumple])=>{
        const item=document.querySelector('[data-regla="'+nombre+'"]');
        item.classList.toggle('text-success',cumple);
        item.classList.toggle('fw-semibold',cumple);
    });
    nuevaClave.setCustomValidity(Object.values(reglas).every(Boolean)?'':'Use al menos 8 caracteres e incluya una letra y un número.');
    const coincide=confirmacion.value===nuevaClave.value;
    confirmacion.setCustomValidity(confirmacion.value&&!coincide?'Las contraseñas no coinciden. Escriba la misma en ambos campos.':'');
    const aviso=document.getElementById('coincidenciaClave');
    aviso.textContent=!confirmacion.value?'Repita exactamente la nueva contraseña.':(coincide?'Las contraseñas coinciden.':'Las contraseñas todavía no coinciden.');
    aviso.classList.toggle('text-success',Boolean(confirmacion.value)&&coincide);
    aviso.classList.toggle('text-danger',Boolean(confirmacion.value)&&!coincide);
    aviso.classList.toggle('text-muted',!confirmacion.value);
}
[nuevaClave,confirmacion].forEach(campo=>campo.addEventListener('input',validarClave));
nuevaClave.form.addEventListener('submit',event=>{validarClave();if(!nuevaClave.form.checkValidity()){event.preventDefault();nuevaClave.form.reportValidity();}});
</script>
@endif
<script src="{{ asset('js/password-visibility.js') }}?v={{ filemtime(public_path('js/password-visibility.js')) }}"></script>
</body></html>
