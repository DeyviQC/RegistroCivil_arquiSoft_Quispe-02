@extends('layouts.app')
@section('titulo', 'Incidencias de actas')
@section('contenido')
<div class="incidencias-page">
    <h1 class="h2 fw-bold mb-2">Incidencias de actas</h1>
    <p class="text-muted mb-4">Consulte las actas que faltan en PDF y las referencias inutilizadas o en blanco.</p>
    <div class="row g-3 mb-4">
        @foreach(['PENDIENTE' => 'Pendientes de PDF', 'INUTILIZADA' => 'Inutilizadas / en blanco'] as $state => $label)
            @php([$unused, $color, $icon] = \App\Services\IncidenciaService::ESTADOS[$state])
            <div class="col-sm-6 col-xl">
                <a class="incidencia-metric card border-0 shadow-sm h-100 text-decoration-none" href="{{ route('incidencias.index', ['situacion' => $state]) }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="incidencia-circle incidencia-{{ $color }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                        <div><div class="fs-2 fw-bold lh-1 mb-2">{{ $counts[$state] ?? 0 }}</div><div class="small">{{ $label }}</div></div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <form action="{{ route('incidencias.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3"><label for="inc-anio" class="form-label">Año</label><select id="inc-anio" name="anio" class="form-select"><option value="">Todos</option>@foreach($years as $year)<option value="{{ $year }}" @selected(($filters['anio'] ?? '') == $year)>{{ $year }}</option>@endforeach</select></div>
            <div class="col-md-3"><label for="inc-tipo" class="form-label">Tipo</label><select id="inc-tipo" name="tipo" class="form-select"><option value="">Todos</option>@foreach(['NACIMIENTO' => 'Nacimiento', 'MATRIMONIO' => 'Matrimonio', 'DEFUNCION' => 'Defunción'] as $value => $label)<option value="{{ $value }}" @selected(($filters['tipo'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><label for="inc-situacion" class="form-label">Situación</label><select id="inc-situacion" name="situacion" class="form-select"><option value="">Todas</option>@foreach(\App\Services\IncidenciaService::ESTADOS_VISIBLES as $value)<option value="{{ $value }}" @selected(($filters['situacion'] ?? '') === $value)>{{ \App\Services\IncidenciaService::ESTADOS[$value][0] }}</option>@endforeach</select></div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-2" aria-hidden="true"></i>Buscar</button><a href="{{ route('incidencias.index') }}" class="btn btn-outline-secondary">Limpiar</a></div>
        </form>
    </div></div>
    <div class="card border-0 shadow-sm mb-3">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 incidencia-table">
            <caption class="px-3">{{ $incidencias->total() }} {{ $incidencias->total() === 1 ? 'incidencia encontrada' : 'incidencias encontradas' }}. Los contadores superiores resumen todo el archivo.</caption>
            <thead><tr><th>Referencia</th><th>Sobre / libro</th><th>Incidencia</th><th>Situación</th><th>Acción</th></tr></thead>
            <tbody>@forelse($incidencias as $incidencia)
                <tr @class(['incidencia-seleccionada' => ($selected['id'] ?? '') === $incidencia['id']])>
                    <td><div class="fw-semibold">{{ ucfirst(mb_strtolower($incidencia['tipo'])) }} {{ $incidencia['anio'] }} · Orden {{ $incidencia['orden'] }}</div><div class="small text-muted">{{ $incidencia['nombre'] }}</div></td>
                    <td>{{ $incidencia['sobre'] }}</td><td>{{ $incidencia['resumen'] }}</td><td>@include('incidencias.badge')</td>
                    <td><a href="{{ route('incidencias.index', array_merge(request()->only('anio','tipo','situacion','page'), ['detalle' => $incidencia['id']])) }}#detalle-incidencia" class="btn btn-sm btn-outline-secondary text-nowrap" aria-label="Ver detalle del orden {{ $incidencia['orden'] }} de {{ $incidencia['anio'] }}">Ver detalle</a></td>
                </tr>
            @empty<tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-check-circle fs-3 d-block mb-2" aria-hidden="true"></i>No hay incidencias con estos filtros.</td></tr>@endforelse</tbody>
        </table></div>
        @if($incidencias->hasPages())<div class="card-footer bg-white">{{ $incidencias->links() }}</div>@endif
    </div>
    @if($selected)
    @php($incidencia = $selected)
    <dialog id="detalle-incidencia" class="incidencia-dialogo" aria-labelledby="detalle-titulo" aria-describedby="detalle-explicacion">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><div class="small text-muted mb-2">DETALLE DE LA INCIDENCIA</div><h2 id="detalle-titulo" class="h4 fw-bold mb-0">{{ ucfirst(mb_strtolower($selected['tipo'])) }} {{ $selected['anio'] }} · Orden {{ $selected['orden'] }}</h2></div><a id="cerrar-incidencia" href="{{ route('incidencias.index', request()->only('anio','tipo','situacion','page')) }}" class="btn btn-outline-secondary btn-sm text-nowrap" autofocus><i class="bi bi-x-lg me-1" aria-hidden="true"></i>Cerrar</a></div>
            <div class="incidencia-persona mb-3"><i class="bi bi-person me-2" aria-hidden="true"></i><strong>{{ $selected['nombre'] ?: 'Sin nombre consignado' }}</strong><div class="small text-muted mt-2">Sobre / libro: {{ $selected['sobre'] }}</div></div>
            @include('incidencias.badge')
            <h3 class="h6 fw-bold mt-4">¿Qué ocurrió?</h3>
            <p id="detalle-explicacion" class="text-muted mb-3">{{ $selected['detalle'] }}</p>
            <div class="incidencia-paso mb-4"><h3 class="h6 fw-bold mb-2">{{ $selected['estado'] === 'PENDIENTE' ? 'Qué falta hacer' : 'Qué debe saber' }}</h3><p class="mb-0">{{ $selected['siguiente'] }}</p></div>
            @if($selected['estado'] === 'PENDIENTE' && $selected['acta'])
                @can('completar', $selected['acta'])
                <form method="POST" action="{{ route('actas.completar-pdf', $selected['acta']) }}" enctype="multipart/form-data" class="incidencia-upload mb-3">
                    @csrf
                    <label for="inc-pdf" class="form-label fw-semibold">Adjuntar el acta que falta</label>
                    <div class="d-flex gap-2 flex-wrap"><input id="inc-pdf" type="file" name="pdf" accept="application/pdf,.pdf" required class="form-control flex-grow-1"><button class="btn btn-primary"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>Adjuntar acta original</button></div>
                    <div class="form-text">Máximo {{ number_format(config('actas.pdf_max_kb', 10240) / 1024, 0) }} MB. Compruebe el nombre y número del acta. Al adjuntarla, el registro quedará completo y bloqueado para edición.</div>
                </form>
                @endcan
            @endif
            <h3 class="h6 fw-bold mt-4">Documento de referencia</h3>
            <p class="small text-muted text-break">{{ $selected['fuente'] }}@if($selected['ruta']) · página {{ $selected['pagina'] }}@endif</p>
            <div class="d-flex gap-2 flex-wrap">
                @if($selected['ruta'])<a href="{{ route('incidencias.documento', $selected['id']) }}#page={{ $selected['pagina'] }}" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>Ver documento recibido</a>@endif
                @if($selected['acta'])<a href="{{ route('actas.show', $selected['acta']) }}" class="btn btn-outline-primary">Ver ficha del acta</a>@endif
            </div>
            <div class="small text-muted mt-3">Las notas y referencias se conservan como antecedentes; no reemplazan el acta original.</div>
        </div>
    </dialog>
    @push('scripts')
    <script>
    (() => {
        const detalle = document.getElementById('detalle-incidencia');
        const cerrar = document.getElementById('cerrar-incidencia');
        if (typeof detalle.showModal === 'function') detalle.showModal();
        else detalle.setAttribute('open', '');
        detalle.addEventListener('cancel', evento => {
            evento.preventDefault();
            window.location.assign(cerrar.href);
        });
    })();
    </script>
    @endpush
    @endif
</div>
@endsection
@push('estilos')
<style>
.incidencia-dialogo { border: 0; border-radius: 20px; padding: 0; width: min(760px, calc(100% - 32px)); max-height: 88vh; max-height: 88dvh; overflow-y: auto; color: #162842; background: #fff; box-shadow: 0 24px 80px #10254233; }
.incidencia-dialogo::backdrop { background: rgb(16 37 66 / 48%); }
.incidencia-dialogo h2, .incidencia-dialogo h3 { color: #102542; }
.incidencia-persona { background: #f5f6f1; border-radius: 12px; padding: 18px; }
.incidencia-paso { background: #f5f8ef; border-left: 4px solid #637443; padding: 16px 18px; border-radius: 0 10px 10px 0; }
body:has(.incidencia-dialogo[open]) { overflow: hidden; }
@media(max-width: 575px) { .incidencia-dialogo .card-body { padding: 20px !important; } }
</style>
@endpush
