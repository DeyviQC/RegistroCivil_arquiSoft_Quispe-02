@extends('layouts.app')

@section('titulo', 'Buscar actas')
@section('descripcion', 'Busque por nombre, DNI, número de acta o ubicación en el archivo. Complete solo los filtros que necesite.')

@section('contenido')
@if(!empty($filtros['tipo']) && !empty($filtros['sobre']))
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0">Sobre {{ $filtros['sobre'] }} · {{ ucfirst(strtolower($filtros['tipo'])) }} <span class="text-muted">({{ $actas->total() }} actas)</span></h5>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('reportes.index',['reporte'=>'sobres','tipo'=>$filtros['tipo']]) }}">Volver a sobres</a>
</div>
@endif
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('actas.index') }}" class="row g-3">
            <div class="col-md-2">
                <label class="form-label small">N° de acta</label>
                <input type="text" name="numero_acta" class="form-control form-control-sm" value="{{ $filtros['numero_acta'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Año</label>
                <input type="number" name="anio" class="form-control form-control-sm" value="{{ $filtros['anio'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="NACIMIENTO" {{ ($filtros['tipo'] ?? '') === 'NACIMIENTO' ? 'selected' : '' }}>Nacimiento</option>
                    <option value="MATRIMONIO" {{ ($filtros['tipo'] ?? '') === 'MATRIMONIO' ? 'selected' : '' }}>Matrimonio</option>
                    <option value="DEFUNCION" {{ ($filtros['tipo'] ?? '') === 'DEFUNCION' ? 'selected' : '' }}>Defunción</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">DNI</label>
                <input type="text" name="dni" class="form-control form-control-sm" value="{{ $filtros['dni'] ?? '' }}">
            </div>
            <div class="col-md-4">
                <label for="buscar-nombre" class="form-label small">Apellidos y nombres</label>
                <input id="buscar-nombre" type="text" name="nombre" class="form-control form-control-sm" value="{{ $filtros['nombre'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Sobre / libro</label>
                <input type="text" name="sobre" class="form-control form-control-sm" value="{{ $filtros['sobre'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Correlativo</label>
                <input type="number" name="correlativo" class="form-control form-control-sm" value="{{ $filtros['correlativo'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Fecha desde</label>
                <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Fecha hasta</label>
                <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
            </div>
            <div class="col-12 d-flex align-items-end gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i> Buscar
                </button>
                <a href="{{ route('actas.index') }}" class="btn btn-outline-secondary">Limpiar filtros</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm busqueda-resultados">
    <div>
        <table class="table table-hover mb-0 align-middle busqueda-tabla">
            <thead class="table-light">
                <tr>
                    <th>Acta</th>
                    <th>Tipo / año</th>
                    <th>Apellidos y nombres</th>
                    <th>Sobre / Correlativo</th>
                    <th>Fecha registro</th>
                    <th>Situación</th>
                    <th class="text-end">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($actas as $acta)
                    <tr>
                        <td data-label="Acta"><div class="fw-semibold">{{ $acta->numero_acta }}</div><div class="small">Orden: {{ $acta->orden_tipo }}</div><div class="small text-muted">{{ ($acta->serie ?? 'DIGITAL') === 'MANUAL' ? 'Manual · libro' : 'Digital · sobre' }}</div></td>
                        <td data-label="Tipo / año">
                            <span class="badge
                                {{ $acta->tipo === 'NACIMIENTO' ? 'text-bg-primary' : ($acta->tipo === 'MATRIMONIO' ? 'text-bg-success' : 'text-bg-secondary') }}">
                                {{ $acta->tipo }}
                            </span>
                            <div class="mt-1">{{ $acta->anio }}</div>
                        </td>
                        <td data-label="Apellidos y nombres">
                            @if ($acta->tipo === 'NACIMIENTO' && $acta->nacimiento)
                                {{ $acta->nacimiento->apellido_nombre }}
                            @elseif ($acta->tipo === 'MATRIMONIO' && $acta->matrimonio)
                                {{ $acta->matrimonio->apellido_nombre }}
                            @elseif ($acta->tipo === 'DEFUNCION' && $acta->defuncion)
                                {{ $acta->defuncion->nombre_completo }}
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Sobre / correlativo" class="small text-muted">{{ $acta->sobre }} / {{ $acta->correlativo }}</td>
                        <td data-label="Fecha registro">{{ $acta->fecha_acta->format('d/m/Y') }}</td>
                        <td data-label="Situación">
                            @if(isset($incidencias[$acta->id]))
                                @php($incidencia = $incidencias[$acta->id])
                                <a href="{{ route('incidencias.index', ['detalle' => $incidencia['id']]) }}#detalle-incidencia" class="text-decoration-none">@include('incidencias.badge')</a>
                            @elseif ($acta->esBorrador())
                                <span class="incidencia-badge incidencia-danger"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i> Pendiente de PDF</span>
                            @else
                                <span class="incidencia-badge incidencia-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Completa</span>
                            @endif
                        </td>
                        <td data-label="Acciones" class="text-end busqueda-acciones">
                            <a href="{{ route('actas.show', $acta) }}" class="btn btn-sm btn-outline-primary" title="Ver">
                                <i class="bi bi-eye" aria-hidden="true"></i> Ver ficha
                            </a>
                            <a href="{{ route('actas.pdf.visor', $acta) }}" class="btn btn-sm btn-outline-secondary" title="PDF">
                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF
                            </a>
                            <a href="{{ route('actas.print', $acta) }}" class="btn btn-sm btn-outline-dark" title="Imprimir" target="_blank">
                                <i class="bi bi-printer" aria-hidden="true"></i> Imprimir
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron actas con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        {{ $actas->links() }}
    </div>
</div>
@endsection
@push('estilos')
<style>
.ui-clear .busqueda-tabla { table-layout:fixed; width:100%; font-size:.9rem; }
.ui-clear .busqueda-tabla th, .ui-clear .busqueda-tabla td { padding:12px 10px; white-space:normal; overflow-wrap:anywhere; }
.busqueda-tabla th:nth-child(1) { width:15%; }
.busqueda-tabla th:nth-child(2) { width:12%; }
.busqueda-tabla th:nth-child(3) { width:23%; }
.busqueda-tabla th:nth-child(4) { width:12%; }
.busqueda-tabla th:nth-child(5) { width:12%; }
.busqueda-tabla th:nth-child(6) { width:13%; }
.busqueda-tabla th:nth-child(7) { width:13%; }
.busqueda-tabla .badge { white-space:normal; font-size:.68rem; }
.busqueda-tabla .incidencia-badge { font-size:.75rem; padding:5px 7px; }
.ui-clear .busqueda-acciones .btn { display:flex; align-items:center; justify-content:center; gap:5px; width:100%; margin:4px 0; padding:5px 6px; font-size:.8rem; }
@media(max-width:1100px) {
    .busqueda-tabla thead { display:none; }
    .busqueda-tabla, .busqueda-tabla tbody { display:block; }
    .busqueda-tabla tr { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); padding:12px; border-bottom:1px solid #dce2ea; }
    .ui-clear .busqueda-tabla td { display:block; border:0; padding:8px; }
    .busqueda-tabla td[data-label]::before { content:attr(data-label); display:block; color:#586676; font-size:.75rem; margin-bottom:5px; font-weight:600; }
    .busqueda-tabla td[data-label="Apellidos y nombres"], .busqueda-tabla .busqueda-acciones, .busqueda-tabla td[colspan] { grid-column:1 / -1; }
    .ui-clear .busqueda-tabla .busqueda-acciones { display:flex; flex-wrap:wrap; gap:8px; text-align:left!important; }
    .busqueda-tabla .busqueda-acciones::before { flex-basis:100%; }
    .ui-clear .busqueda-acciones .btn { width:auto; flex:1; }
}
</style>
@endpush
