@extends('layouts.app')

@section('titulo', 'Inicio')
@section('descripcion', 'Consulte el total de actas, los últimos registros y las actividades recientes.')

@section('contenido')
<nav class="dashboard-actions" aria-label="Acciones frecuentes"><a href="{{ route('atencion.index') }}" class="btn btn-primary"><i class="bi bi-inbox me-2" aria-hidden="true"></i>Atender solicitudes</a><a href="{{ route('actas.index') }}" class="btn btn-outline-primary"><i class="bi bi-search me-2" aria-hidden="true"></i>Buscar un acta</a><a href="{{ route('actas.create') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-plus me-2" aria-hidden="true"></i>Registrar acta</a></nav>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card card-metric metric-nacimiento border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Nacimientos</div>
                <div class="fs-2 fw-bold text-primary">{{ number_format($estadisticas['nacimientos']) }}</div>
                <i class="bi bi-person-plus fs-3 text-primary-subtle position-absolute top-0 end-0 m-3"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-metric metric-matrimonio border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Matrimonios</div>
                <div class="fs-2 fw-bold text-success">{{ number_format($estadisticas['matrimonios']) }}</div>
                <i class="bi bi-heart fs-3 text-success-subtle position-absolute top-0 end-0 m-3"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-metric metric-defuncion border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Defunciones</div>
                <div class="fs-2 fw-bold text-secondary">{{ number_format($estadisticas['defunciones']) }}</div>
                <i class="bi bi-flower1 fs-3 text-secondary-subtle position-absolute top-0 end-0 m-3"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-metric border-0 shadow-sm text-white" style="background-color: #15803d;">
            <div class="card-body">
                <div class="small opacity-75">Total de Actas</div>
                <div class="fs-2 fw-bold">{{ number_format($estadisticas['total']) }}</div>
                <i class="bi bi-archive fs-3 opacity-50 position-absolute top-0 end-0 m-3"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 dashboard-overview">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock-history me-1"></i> Últimas actas registradas
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Tipo</th>
                            <th>N° / Año</th>
                            <th>Registrado por</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ultimasActas as $acta)
                            <tr>
                                <td><span class="badge text-bg-light">{{ $acta->codigo }}</span></td>
                                <td>
                                    <span class="badge
                                        {{ $acta->tipo === 'NACIMIENTO' ? 'text-bg-primary' : ($acta->tipo === 'MATRIMONIO' ? 'text-bg-success' : 'text-bg-secondary') }}">
                                        {{ $acta->tipo }}
                                    </span>
                                </td>
                                <td>{{ $acta->numero_acta }} / {{ $acta->anio }}</td>
                                <td>{{ $acta->usuario->name }}</td>
                                <td class="text-end">
                                    <a href="{{ route('actas.show', $acta) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver acta {{ $acta->numero_acta }} de {{ $acta->anio }}">
                                        <i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">Ver acta</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Aún no hay actas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-activity me-1"></i> Actividad reciente
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($actividadReciente as $registro)
                    <li class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold small">{{ $registro->accion_legible }}</div>
                            <div class="text-muted small">{{ $registro->usuario->name ?? 'Sistema' }} — {{ $registro->descripcion }}</div>
                        </div>
                        <small class="text-muted text-nowrap ms-2">{{ $registro->created_at->diffForHumans() }}</small>
                    </li>
                @empty
                    <li class="list-group-item text-center text-muted py-4">Sin actividad registrada.</li>
                @endforelse
            </ul>
        </div>

        @if (count($actasPorAnio))
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-bar-chart me-1"></i> Actas por año
            </div>
            <div class="card-body">
                @foreach ($actasPorAnio as $anio => $total)
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ $anio }}</span>
                        <span class="fw-semibold">{{ $total }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
