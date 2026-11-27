@if($counts['PENDIENTE'] ?? 0)
<div class="incidencia-aviso mb-4 d-flex gap-3 align-items-center flex-wrap" role="status">
    <i class="bi bi-exclamation-triangle-fill fs-2" aria-hidden="true"></i>
    <div class="flex-grow-1">
        @if($counts['PENDIENTE'] ?? 0)<strong>{{ $counts['PENDIENTE'] }} {{ $counts['PENDIENTE'] == 1 ? 'acta pendiente' : 'actas pendientes' }} del documento original</strong>@endif
        <div class="small mt-1">Consulte el detalle y adjunte los documentos cuando estén disponibles.</div>
    </div>
    <a href="{{ route('incidencias.index', ['situacion' => 'PENDIENTE']) }}" class="btn btn-outline-danger">Revisar incidencias</a>
</div>
@endif
