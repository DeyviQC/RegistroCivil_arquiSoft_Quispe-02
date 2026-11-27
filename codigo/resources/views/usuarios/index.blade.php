@extends('layouts.app')

@section('titulo', 'Usuarios')
@section('descripcion', 'Administre las cuentas, sus permisos y el acceso al sistema.')

@section('contenido')
<div class="d-flex justify-content-between mb-3">
    <h2 class="h5 mb-0">Cuentas del sistema</h2>
    <a href="{{ route('usuarios.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i> Nuevo usuario
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
                @foreach ($usuarios as $usuario)
                    <tr>
                        <td>{{ $usuario->username }}</td>
                        <td><div class="d-flex align-items-center gap-3"><span class="rounded-circle d-inline-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width:42px;height:42px;background:#edf0e6;color:#43512c;font-weight:600">@if($usuario->foto)<img src="{{ route('usuarios.foto', $usuario) }}" alt="Foto de {{ $usuario->name }}" loading="lazy" style="width:100%;height:100%;object-fit:cover">@else{{ mb_strtoupper(mb_substr($usuario->name, 0, 1)) }}@endif</span><span>{{ $usuario->name }}</span></div></td>
                        <td>
                            @foreach ($usuario->roles as $rol)
                                <span class="badge text-bg-info-subtle text-info-emphasis">{{ $rol->nombre }}</span>
                            @endforeach
                        </td>
                        <td>
                            @if ($usuario->is_active)
                                <span class="badge text-bg-success">Activo</span>
                            @else
                                <span class="badge text-bg-danger"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i> Inactivo</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                            </a>
                            @if ($usuario->id !== auth()->id())
                                <form method="POST" action="{{ route('usuarios.alternar-estado', $usuario) }}" class="d-inline"
                                      data-confirm="{{ $usuario->is_active ? '¿Confirma desactivar este usuario? No podrá acceder al sistema.' : '¿Confirma activar este usuario?' }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-outline-{{ $usuario->is_active ? 'danger' : 'success' }}" title="{{ $usuario->is_active ? 'Desactivar usuario' : 'Activar usuario' }}" aria-label="{{ $usuario->is_active ? 'Desactivar usuario' : 'Activar usuario' }}">
                                        <i class="bi bi-{{ $usuario->is_active ? 'slash-circle' : 'check-circle' }}"></i>
                                        {{ $usuario->is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $usuarios->links() }}</div>
</div>
@endsection
