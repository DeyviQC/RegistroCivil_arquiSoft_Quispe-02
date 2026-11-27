@extends('layouts.app')
@section('titulo', 'Mi perfil')
@section('descripcion', 'Actualice su información personal. Las credenciales y los permisos son gestionados por el administrador.')
@section('contenido')
<div class="card border-0 shadow-sm profile-settings" style="max-width:760px"><div class="card-body p-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center justify-content-center rounded-circle overflow-hidden flex-shrink-0" style="width:96px;height:96px;background:#edf0e6;color:#43512c;font-size:2rem">
            @if($usuario->foto)<img id="vista-foto" src="{{ route('perfil.foto') }}" alt="Foto de perfil" style="width:100%;height:100%;object-fit:cover">@else<span id="inicial-foto">{{ mb_strtoupper(mb_substr($usuario->name,0,1)) }}</span><img id="vista-foto" alt="Vista previa de la foto" style="width:100%;height:100%;object-fit:cover" hidden>@endif
        </div>
        <div><h2 class="h5 mb-1">{{ $usuario->name }}</h2><p class="text-muted mb-0">{{ $usuario->username }} · {{ $usuario->isAdmin() ? 'Administrador' : 'Asistente' }}</p></div>
    </div>
    <form method="POST" action="{{ route('perfil.guardar') }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <section class="profile-settings-section"><h3>Datos personales</h3><div class="mb-3"><label for="perfil-nombre" class="form-label">Nombre completo</label><input id="perfil-nombre" name="name" value="{{ old('name', $usuario->name) }}" maxlength="150" required class="form-control" autocomplete="name"></div></section><section class="profile-settings-section"><h3>Acceso a la cuenta</h3>
        @if($usuario->isAdmin())
        <div class="mb-3"><label for="perfil-usuario" class="form-label">Usuario para iniciar sesión</label><input id="perfil-usuario" name="username" value="{{ old('username', $usuario->username) }}" maxlength="50" required class="form-control" autocomplete="username"></div>
        <div class="mb-4"><label for="perfil-correo" class="form-label">Correo de recuperación</label><input id="perfil-correo" type="email" name="email" value="{{ old('email', $usuario->email) }}" maxlength="150" required class="form-control" autocomplete="email" aria-describedby="ayuda-correo-perfil"><p id="ayuda-correo-perfil" class="form-text">Se utiliza para recuperar el acceso a su cuenta de administrador.</p></div>
        @else<dl class="profile-access-data"><div><dt>Usuario</dt><dd>{{ $usuario->username }}</dd></div><div><dt>Rol</dt><dd>Asistente</dd></div></dl><p class="small text-muted">El administrador le proporciona su usuario y contraseña. Para cambiarlos, comuníquese con él.</p>@endif</section><section class="profile-settings-section"><h3>Foto opcional</h3>
        <label for="foto-perfil" class="form-label fw-semibold">Foto de perfil (opcional)</label>
        <input id="foto-perfil" name="foto" type="file" accept="image/jpeg,image/png,image/webp" class="form-control" aria-describedby="ayuda-foto">
        <div id="ayuda-foto" class="form-text mb-4">JPG, PNG o WEBP. Máximo 2 MB. La foto aparecerá junto a su nombre.</div>
        <p id="estado-foto" class="small text-muted" role="status" aria-live="polite">Su foto actual se conserva si no selecciona otra.</p>
        @if($usuario->foto)<div class="form-check mb-3"><input id="perfil-quitar" type="checkbox" class="form-check-input" name="quitar_foto" value="1"><label for="perfil-quitar" class="form-check-label">Quitar mi foto actual</label></div>@endif
        </section>@if($usuario->isAdmin())<section class="profile-settings-section"><h3>Seguridad</h3><details class="mb-4" @if($errors->hasAny(['password','password_confirmation','current_password'])) open @endif><summary class="fw-semibold">Cambiar contraseña (opcional)</summary><div class="mt-3"><label for="clave-actual" class="form-label">Contraseña actual</label><input id="clave-actual" type="password" name="current_password" class="form-control mb-3" autocomplete="current-password"><label for="clave-nueva" class="form-label">Nueva contraseña</label><input id="clave-nueva" type="password" name="password" class="form-control" autocomplete="new-password"><div class="form-text mb-3">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</div><label for="clave-confirmar" class="form-label">Confirmar nueva contraseña</label><input id="clave-confirmar" type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div></details><p class="form-text">Las credenciales de los asistentes se administran desde <a href="{{ route('usuarios.index') }}">Usuarios</a>.</p></section>@endif
        <button id="guardar-foto" type="submit" class="btn btn-primary"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Guardar cambios</button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Volver</a>
    </form>
</div></div>
@endsection
@push('scripts')
<script>
(() => {
    const entrada = document.getElementById('foto-perfil');
    const imagen = document.getElementById('vista-foto');
    const guardar = document.getElementById('guardar-foto');
    const estado = document.getElementById('estado-foto');
    let url;
    entrada.addEventListener('change', () => {
        const archivo = entrada.files[0];
        const error = archivo && (!['image/jpeg','image/png','image/webp'].includes(archivo.type) ? 'Seleccione una foto JPG, PNG o WEBP.' : archivo.size > 2 * 1024 * 1024 ? 'La foto debe pesar como máximo 2 MB.' : '');
        entrada.setCustomValidity(error || '');
        guardar.disabled = !!error;
        if (error) { estado.textContent = error; entrada.reportValidity(); return; }
        if (url) URL.revokeObjectURL(url);
        if (!archivo) { estado.textContent = 'No se cambiará su foto.'; return; }
        url = URL.createObjectURL(archivo);
        imagen.src = url;
        imagen.hidden = false;
        estado.textContent = 'Vista previa lista. Pulse «Guardar cambios» para aplicar la foto.';
        const inicial = document.getElementById('inicial-foto');
        if (inicial) inicial.hidden = true;
    });
})();
</script>
@endpush
