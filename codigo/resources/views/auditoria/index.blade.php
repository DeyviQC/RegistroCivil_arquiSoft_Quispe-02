@extends('layouts.app')

@section('titulo', 'Historial de actividades')
@section('descripcion', 'Consulte quién realizó cada actividad y cuándo. Use los filtros para encontrar lo que necesita.')

@section('contenido')
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('auditoria.index') }}" class="row g-3">
            <div class="col-md-2">
                <label class="form-label small">Actividad</label>
                <select name="accion" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach (\App\Models\Auditoria::ACCIONES as $accion => $nombre)
                        <option value="{{ $accion }}" {{ ($filtros['accion'] ?? '') === $accion ? 'selected' : '' }}>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Sección del sistema</label>
                <select name="modulo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($modulos as $modulo)
                        <option value="{{ $modulo }}" @selected(($filtros['modulo'] ?? '') === $modulo)>{{ \App\Models\Auditoria::nombreModulo($modulo) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Fecha desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Fecha hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
            </div>
            <div class="col-12 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" aria-label="Buscar actividades"><i class="bi bi-search me-1" aria-hidden="true"></i> Buscar actividades</button>
                <a href="{{ route('auditoria.index') }}" class="btn btn-outline-secondary">Limpiar filtros</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Fecha</th><th>Usuario</th><th>Actividad</th><th>Sección del sistema</th><th>Detalle</th><th title="Dirección de red desde la que se realizó la actividad">Dirección de conexión</th></tr>
            </thead>
            <tbody>
                @forelse ($registros as $registro)
                    <tr>
                        <td class="text-nowrap">{{ $registro->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $registro->usuario->name ?? 'Sistema' }}</td>
                        <td><span class="badge text-bg-light border">{{ $registro->accion_legible }}</span></td>
                        <td>{{ \App\Models\Auditoria::nombreModulo($registro->modulo ?? '') }}</td>
                        <td class="small">{{ $registro->descripcion }}</td>
                        <td class="small text-muted">{{ $registro->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Todavía no hay actividades registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $registros->links() }}</div>
</div>
@endsection
