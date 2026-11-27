@extends('layouts.app')
@section('titulo', 'Atención de solicitud')
@push('estilos')
<link rel="stylesheet" href="{{ asset('css/atencion.css') }}?v={{ filemtime(public_path('css/atencion.css')) }}">
@endpush
@section('contenido')
@php
$d = $tramite->datos;
$estados = App\Models\TramitePublico::ESTADOS;
$transiciones = App\Models\TramitePublico::TRANSICIONES[$tramite->estado] ?? [];
$fecha = function ($valor, string $formato = 'd/m/Y') {
    if (!$valor) return null;
    try { return \Illuminate\Support\Carbon::parse($valor)->timezone(config('app.timezone'))->format($formato); } catch (\Throwable) { return (string) $valor; }
};
$subsanaciones = $d['subsanaciones'] ?? [];
$cruces = ['COINCIDE'=>'Los datos de filiación coinciden','NO_COINCIDE'=>'El vínculo requiere aclaración','SIN_DATOS'=>'Faltan datos para contrastar el vínculo','NO_APLICA'=>'Revisión del solicitante'];
@endphp
<div class="review-workspace">
<a class="review-back" href="{{ route('atencion.index') }}"><i class="bi bi-arrow-left"></i> Volver a solicitudes</a>
<header class="review-header"><div><p class="review-kicker">REGISTRO CIVIL · ATENCIÓN CIUDADANA</p><h1>Solicitud N.º {{ str_pad((string) $tramite->id, 6, '0', STR_PAD_LEFT) }}</h1><p class="review-muted mb-0">Recibida el {{ $tramite->created_at->format('d/m/Y · H:i') }}</p></div><span class="review-status">{{ $estados[$tramite->estado] }}</span></header>
<div class="review-facts"><span><i class="bi bi-file-earmark-text"></i>{{ ucfirst(strtolower($d['busqueda']['tipo'] ?? $tramite->acta->tipo)) }}</span><span><i class="bi bi-credit-card"></i>{{ $tramite->pago?->estado === 'PAGADO' ? 'Pago confirmado' : 'Pago pendiente' }} · S/ {{ number_format(($tramite->pago?->importe_centimos ?? 1030)/100, 2) }}</span></div>
<div class="review-columns"><article>
<section class="review-panel">
<div class="review-section-title"><h2>Acta solicitada</h2><a href="{{ route('actas.pdf', $tramite->acta) }}" target="_blank" rel="noopener">Ver acta original <i class="bi bi-arrow-up-right"></i></a></div>
<dl class="review-data"><div class="wide"><dt>Titular</dt><dd class="review-person">{{ $d['busqueda']['titular'] ?? 'No consignado' }}</dd></div><div><dt>Fecha del hecho</dt><dd>{{ $fecha($d['busqueda']['fecha'] ?? null) ?? 'No consignada' }}</dd></div><div><dt>Relación declarada</dt><dd>{{ App\Models\TramitePublico::RELACIONES[$d['relacion'] ?? ''] ?? 'No informada' }}</dd></div></dl>
<div class="review-divider"></div><h2>Solicitante</h2><dl class="review-data"><div class="wide"><dt>Nombre y apellidos</dt><dd>{{ $d['nombre'] ?? 'No informado' }}</dd></div><div><dt>DNI declarado</dt><dd>{{ $d['dni'] ?? 'No informado' }}</dd></div><div><dt>Correo</dt><dd>{{ $tramite->email }}</dd></div></dl>
<div class="review-note"><strong>Motivo de la solicitud</strong><p>{{ $d['motivo'] ?? 'No informado' }}</p></div>
<details class="review-details"><summary>Más datos del solicitante</summary><dl class="review-data mt-3"><div><dt>Nacimiento declarado</dt><dd>{{ $fecha($d['nacimiento'] ?? null) ?? 'No informado' }}</dd></div><div><dt>WhatsApp</dt><dd>{{ $d['whatsapp'] ?? 'No registrado' }}</dd></div><div><dt>DNI del titular declarado</dt><dd>{{ $d['dni_titular'] ?? 'No informado' }}</dd></div><div><dt>Contraste del DNI</dt><dd>{{ $tramite->identidad_estado === 'DATOS_CONTRASTADOS' ? 'Datos contrastados; no acredita identidad por sí solo' : 'Pendiente de revisión' }}</dd></div></dl></details>
</section>
@if($subsanaciones)
<section class="review-panel"><h2>Respuestas del ciudadano a las observaciones</h2>
@foreach(array_reverse($subsanaciones, true) as $i => $s)
<div class="review-note mb-2"><small class="review-muted">{{ $fecha($s['enviado_at'] ?? null, 'd/m/Y · H:i') }}</small><p class="mb-1">{{ $s['texto'] }}</p>
@if(!empty($s['adjunto']))<a href="{{ route('atencion.subsanacion', [$tramite, $i]) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> {{ $s['nombre_adjunto'] ?? 'Documento adjunto' }}</a>@endif</div>
@endforeach
</section>
@endif
@if($tramite->requiereRevisionVinculo() || $cruceActual['resultado'] === 'NO_COINCIDE')
<div class="review-callout mb-4"><i class="bi bi-exclamation-circle"></i><div><strong>{{ $cruces[$cruceActual['resultado']] }}</strong><p class="mb-0">Revisa el parentesco o representación y su sustento antes de autorizar la entrega.</p></div></div>
@endif
<details class="review-panel review-disclosure"><summary>Revisión y documentos de sustento</summary><div class="review-disclosure-body"><p class="review-muted">El contraste de datos no autentica al solicitante ni autoriza la entrega.</p>
@if(isset($d['cruce_parentesco']))<p class="small">Al presentar: {{ $cruces[$d['cruce_parentesco']['resultado']] }}. @if($d['cruce_parentesco']['filiacion_version'] !== $cruceActual['filiacion_version'])La filiación cambió; el contraste actual está actualizado.@endif</p>@endif
@if(isset($d['declaracion_detalle']))<details class="review-details"><summary>Declaración aceptada por el ciudadano</summary><p>{{ $d['declaracion_detalle']['texto'] }}</p><small>Versión {{ $d['declaracion_detalle']['version'] }} · {{ $fecha($d['declaracion_detalle']['aceptada_at'], 'd/m/Y · H:i') }}</small></details>@endif
@if(isset($d['revision_vinculo']))<div class="review-note"><strong>Sustento registrado</strong><p>{{ $d['revision_vinculo']['sustento'] }}</p></div>@else<p class="review-muted mb-0">No se ha registrado sustento adicional.</p>@endif
</div></details>
<details class="review-panel review-disclosure"><summary>Historial de atención <span class="review-count">{{ $tramite->eventos->count() }}</span></summary><div class="review-disclosure-body review-timeline">@forelse($tramite->eventos as $evento)<article><small>{{ $evento->created_at->format('d/m/Y · H:i') }}</small><h3>{{ $estados[$evento->estado] ?? $evento->estado }}</h3><p>{{ $evento->mensaje }}</p></article>@empty<p class="review-muted">Sin respuestas registradas.</p>@endforelse</div></details>
</article><aside><section class="review-panel review-decision"><h2 class="mb-4">{{ $tramite->estado === 'LISTA' ? 'Documento entregable' : ($tramite->estado === 'APROBADA' ? 'Firma y entrega' : 'Atender solicitud') }}</h2>
@if(in_array($tramite->estado, ['APROBADA', 'LISTA']))
@include('portal.firma')
@elseif(count($transiciones))
<form method="POST" action="{{ route('atencion.atender', $tramite) }}" data-decision>@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $tramite->version }}">
@if(in_array('APROBADA', $transiciones, true) && $tramite->requiereRevisionVinculo())<fieldset class="review-vinculo" id="revision-vinculo"><legend>Revisión del vínculo</legend><p class="review-muted">Necesario solo para aprobar.</p><div class="form-check mb-3"><input type="checkbox" name="vinculo_revisado" id="vinculo_revisado" class="form-check-input" value="1" @checked(old('vinculo_revisado'))><label for="vinculo_revisado" class="form-check-label">Revisé el vínculo y corresponde autorizar la entrega.</label></div><label for="sustento_revision" class="form-label">Sustento de la revisión</label><textarea name="sustento_revision" id="sustento_revision" class="form-control" rows="2" maxlength="1500">{{ old('sustento_revision') }}</textarea></fieldset>@endif
@if(in_array('APROBADA', $transiciones, true))
<button name="estado" value="APROBADA" class="btn btn-success btn-lg w-100 mt-2"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>Aprobar solicitud</button>
<p class="review-muted small mt-2">Se avisará al ciudadano y pasarás a la firma.</p>
@endif
<details class="mt-3" @if($errors->has('mensaje') || old('mensaje')) open @endif><summary class="fw-semibold">Observar o rechazar</summary>
<label for="mensaje" class="form-label mt-3">Motivo para el ciudadano</label>
<div class="d-flex flex-wrap gap-1 mb-2" aria-label="Motivos frecuentes">@foreach(config('portal.plantillas_observacion', []) as $titulo => $texto)<button type="button" class="btn btn-sm btn-outline-secondary" data-plantilla="{{ $texto }}">{{ $titulo }}</button>@endforeach</div>
<textarea id="mensaje" name="mensaje" class="form-control @error('mensaje') is-invalid @enderror" rows="3" maxlength="2000" placeholder="Ej.: Falta acreditar el vínculo con el titular. Envía una copia de tu DNI.">{{ old('mensaje') }}</textarea>
@error('mensaje')<div class="invalid-feedback">{{ $message }}</div>@enderror
<p class="review-muted small mt-1">Se mostrará en su seguimiento y le llegará por correo.</p>
<div class="d-flex gap-2">
@if(in_array('OBSERVADA', $transiciones, true))<button name="estado" value="OBSERVADA" class="btn btn-outline-warning flex-fill" data-requiere-motivo><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Observar</button>@endif
@if(in_array('RECHAZADA', $transiciones, true))<button name="estado" value="RECHAZADA" class="btn btn-outline-danger flex-fill" data-requiere-motivo data-confirmar-rechazo><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Rechazar</button>@endif
</div>
</details>
</form>
@else<p class="review-muted mb-0">Esta solicitud no tiene acciones pendientes.</p>@endif
</section></aside></div></div>
@endsection
@push('scripts')
<script>
(() => {
    // Observing or rejecting needs a reason; approving does not.
    const form = document.querySelector('[data-decision]');
    if (!form) return;
    const motivo = form.querySelector('#mensaje');
    form.querySelectorAll('[data-requiere-motivo]').forEach(boton => boton.addEventListener('click', event => {
        if (!motivo.value.trim()) {
            event.preventDefault();
            motivo.closest('details').open = true;
            motivo.classList.add('is-invalid');
            motivo.focus();
            return;
        }
        if (boton.hasAttribute('data-confirmar-rechazo') && !confirm('¿Rechazar la solicitud? Esta decisión no se puede deshacer.')) event.preventDefault();
    }));
    motivo.addEventListener('input', () => motivo.classList.remove('is-invalid'));
    // Frequent reasons: one click writes the text, which can still be edited.
    form.querySelectorAll('[data-plantilla]').forEach(boton => boton.addEventListener('click', () => {
        motivo.value = boton.dataset.plantilla;
        motivo.classList.remove('is-invalid');
        motivo.focus();
    }));
})();
</script>
@endpush
