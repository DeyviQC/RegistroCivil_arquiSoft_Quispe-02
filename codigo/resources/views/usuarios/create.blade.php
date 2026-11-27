@extends('layouts.app')
@section('titulo', 'Nuevo usuario')
@section('descripcion', 'Complete los datos de la persona y seleccione el rol que tendrá en el sistema.')
@section('contenido')
<div class="card border-0 shadow-sm" style="max-width: 600px;">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('usuarios.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Nombre completo</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Nombre de usuario para iniciar sesión</label>
                <input type="text" name="username" class="form-control" value="{{ old('username') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña</label><p class="form-text">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</p>
                <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" class="form-control" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Rol</label>
                <select name="role" class="form-select" required>
                    <option value="">Seleccione…</option>
                    <option value="administrador" {{ old('role') === 'administrador' ? 'selected' : '' }}>Administrador</option>
                    <option value="asistente" {{ old('role') === 'asistente' ? 'selected' : '' }}>Asistente</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Crear usuario</button>
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
