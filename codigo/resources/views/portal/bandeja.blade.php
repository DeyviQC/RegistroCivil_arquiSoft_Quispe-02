@extends('layouts.app')
@section('titulo', 'Solicitudes ciudadanas')
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/bandeja-ciudadana.css') }}?v={{ filemtime(public_path('css/bandeja-ciudadana.css')) }}">
@endpush
@section('contenido')
@php
$etiquetas = ['PENDIENTE'=>'Por atender','EN_REVISION'=>'En revisión','OBSERVADA'=>'Observada','APROBADA'=>'Pendiente de firma','LISTA'=>'Documento disponible','RECHAZADA'=>'Rechazada'];
$colores = ['PENDIENTE'=>'amber','EN_REVISION'=>'blue','OBSERVADA'=>'amber','APROBADA'=>'blue','LISTA'=>'green','RECHAZADA'=>'gray'];
@endphp
<div class="desk-inbox">
<header class="desk-inbox-heading"><div><p>REGISTRO CIVIL · MESA DE ATENCIÓN</p><h1>Solicitudes ciudadanas</h1><span>Revisa cada solicitud y continúa con su atención.</span></div><span class="desk-paid"><i class="bi bi-check-circle"></i> Solo pagos confirmados</span></header>
<nav class="desk-summary" aria-label="Resumen de solicitudes">
<a href="{{ route('atencion.index', ['estado'=>'PENDIENTE']) }}"><span>Por atender</span><strong>{{ $resumen['PENDIENTE'] ?? 0 }}</strong></a>
<a href="{{ route('atencion.index', ['estado'=>'APROBADA']) }}"><span>Pendientes de firma</span><strong>{{ $resumen['APROBADA'] ?? 0 }}</strong></a>
<a href="{{ route('atencion.index') }}"><span>Total de solicitudes</span><strong>{{ $resumen->sum() }}</strong></a>
</nav>
<section class="desk-list" aria-label="Bandeja de solicitudes">
<form method="GET" action="{{ route('atencion.index') }}" class="desk-filters">
<div><label for="bandeja-estado">Estado</label><select id="bandeja-estado" name="estado" class="form-select"><option value="">Todos los estados</option>@foreach($etiquetas as $valor=>$etiqueta)<option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>@endforeach</select></div>
<div><label for="bandeja-numero">N.º de solicitud</label><input id="bandeja-numero" name="solicitud" class="form-control" type="number" min="1" max="2147483647" placeholder="Ej. 6" value="{{ $filtros['solicitud'] ?? '' }}"></div>
<div><label for="bandeja-dni">DNI del solicitante</label><input id="bandeja-dni" name="dni" class="form-control" inputmode="numeric" maxlength="8" pattern="[0-9]{8}" placeholder="8 dígitos" value="{{ $filtros['dni'] ?? '' }}"></div>
<button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Buscar</button>
@if(!empty($filtros['estado']) || !empty($filtros['solicitud']) || !empty($filtros['dni']))<a href="{{ route('atencion.index') }}" class="desk-clear">Limpiar filtros</a>@endif
</form>
<div class="desk-list-caption"><strong>{{ $tramites->total() }} {{ $tramites->total() === 1 ? 'solicitud' : 'solicitudes' }}</strong><span>Más recientes primero</span></div>
<table class="desk-table"><thead><tr><th scope="col">Solicitud</th><th scope="col">Solicitante y documento</th><th scope="col">Estado</th><th scope="col"><span class="visually-hidden">Acción</span></th></tr></thead><tbody>
@forelse($tramites as $tramite)
@php($datos = $tramite->datos)
<tr><td data-label="Solicitud"><strong class="desk-number">N.º {{ str_pad((string)$tramite->id, 6, '0', STR_PAD_LEFT) }}</strong><span class="desk-sub">{{ $tramite->created_at->format('d/m/Y · H:i') }}</span></td>
<td data-label="Solicitante"><strong class="desk-person">{{ $datos['nombre'] ?? 'No informado' }}</strong><span class="desk-sub">{{ ucfirst(strtolower($datos['busqueda']['tipo'] ?? 'Acta')) }}</span></td>
<td data-label="Estado"><span class="desk-state desk-state-{{ $colores[$tramite->estado] ?? 'gray' }}"><span aria-hidden="true"></span>{{ $etiquetas[$tramite->estado] ?? App\Models\TramitePublico::ESTADOS[$tramite->estado] }}</span>@if($tramite->estado === 'PENDIENTE' && !empty($datos['subsanaciones']))<span class="desk-sub"><i class="bi bi-reply" aria-hidden="true"></i> Respondió la observación</span>@endif</td>
<td class="desk-action"><a class="btn btn-outline-primary" href="{{ route('atencion.show', $tramite) }}" aria-label="{{ in_array($tramite->estado, ['LISTA','RECHAZADA']) ? 'Ver' : 'Atender' }} solicitud {{ $tramite->id }}">{{ in_array($tramite->estado, ['LISTA','RECHAZADA']) ? 'Ver detalle' : 'Atender' }}<i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a></td></tr>
@empty
<tr><td colspan="4"><div class="desk-empty"><i class="bi bi-inbox" aria-hidden="true"></i><h2>No hay solicitudes para mostrar</h2><p>{{ !empty($filtros['estado']) || !empty($filtros['solicitud']) || !empty($filtros['dni']) ? 'Prueba con otro número o DNI, o cambia el estado seleccionado.' : 'Las solicitudes aparecerán aquí cuando se confirme su pago.' }}</p></div></td></tr>
@endforelse
</tbody></table>
@if($tramites->hasPages())<div class="desk-pagination">{{ $tramites->links() }}</div>@endif
</section></div>
@endsection
