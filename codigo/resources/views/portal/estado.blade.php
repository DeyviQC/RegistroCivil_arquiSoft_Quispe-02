@extends('portal.layout')
@section('titulo', 'Mi trámite')
@section('contenido')
@if($tramite->estado === 'LISTA')
<section class="card border-success mb-4"><div class="card-body p-4"><span class="citizen-eyebrow">TU ACTA YA ESTÁ LISTA</span><h2 class="h4 mt-2">Descarga tu documento firmado</h2><p>La revisión municipal ha finalizado. Guarda el PDF original para conservar su firma digital.</p><a href="{{ route('portal.documento') }}" class="btn btn-primary"><i class="bi bi-download me-2"></i>Descargar mi acta</a></div></section>
@endif
<div class="citizen-banner"><span class="citizen-eyebrow">SEGUIMIENTO DE SOLICITUD</span><h1>Solicitud N.º {{ str_pad((string) $tramite->id, 6, '0', STR_PAD_LEFT) }}</h1><p>Consulta el avance y las respuestas de Registro Civil.</p></div>
@php
$ultimoMensaje = $tramite->eventos->where('estado', $tramite->estado)->last()?->mensaje;
// The next thing the citizen must do goes first: pay, then choose how to receive the act.
$porPagar = $tramite->estado === 'PENDIENTE_PAGO' && $tramite->pago?->estado !== 'PAGADO';
$eligeEntrega = $tramite->pago?->estado === 'PAGADO' && empty($tramite->datos['canales_entrega']) && ! in_array($tramite->estado, ['RECHAZADA', 'VENCIDA'], true);
@endphp
@if($porPagar)
<section class="card border-primary mb-4" id="pagar" aria-labelledby="titulo-pagar"><div class="card-body p-4">
<span class="citizen-eyebrow">ÚLTIMO PASO</span>
<h2 class="h4 mt-2" id="titulo-pagar">Paga tu trámite para que lo revisemos</h2>
<div class="mx-auto" style="max-width:520px">@include('portal.pago')</div>
</div></section>
@elseif($eligeEntrega)
<section class="card border-success mb-4" id="entrega"><div class="card-body p-4">
<span class="citizen-eyebrow">PAGO CONFIRMADO · ÚLTIMO PASO</span>
<div class="mx-auto" style="max-width:520px">@include('portal.consulta-whatsapp')</div>
</div></section>
@endif
@if($tramite->estado === 'OBSERVADA')
<section class="card border-warning mb-4" id="responder-observacion"><div class="card-body p-4">
<span class="citizen-eyebrow">TU SOLICITUD TIENE UNA OBSERVACIÓN</span>
<h2 class="h4 mt-2">Registro Civil necesita que completes algo</h2>
@if($ultimoMensaje)<blockquote class="border-start border-4 border-warning ps-3 my-3">{{ $ultimoMensaje }}</blockquote>@endif
<form method="POST" action="{{ route('portal.responder') }}" enctype="multipart/form-data">@csrf
<label for="respuesta" class="form-label fw-semibold">Tu respuesta</label>
<textarea id="respuesta" name="respuesta" class="form-control mb-3" rows="3" minlength="10" maxlength="1500" required placeholder="Explica lo que te pidieron o indica qué documento adjuntas.">{{ old('respuesta') }}</textarea>
<label for="adjunto" class="form-label fw-semibold">Adjuntar documento <span class="fw-normal text-muted">(opcional · PDF, JPG o PNG · máx. 5 MB)</span></label>
<input id="adjunto" name="adjunto" type="file" accept="application/pdf,image/jpeg,image/png" class="form-control mb-3">
<button class="btn btn-primary w-100"><i class="bi bi-send me-2" aria-hidden="true"></i>Enviar respuesta</button>
<p class="small text-muted mt-2 mb-0">Tu solicitud volverá a la bandeja del Registro Civil para continuar con su revisión.</p>
</form>
</div></section>
@elseif($tramite->estado === 'RECHAZADA')
<div class="alert alert-danger mb-4"><strong>Tu solicitud fue rechazada.</strong>@if($ultimoMensaje)<br>Motivo: {{ $ultimoMensaje }}@endif<br><span class="small">Si tienes dudas sobre la decisión o sobre tu pago, comunícate con Registro Civil indicando la solicitud N.º {{ str_pad((string) $tramite->id, 6, '0', STR_PAD_LEFT) }}.</span></div>
@endif
@if(session('portal_consultas'))<a href="{{ route('portal.solicitudes') }}" class="btn btn-outline-secondary mb-4"><i class="bi bi-arrow-left me-2"></i>Ver mis solicitudes</a>@endif
<div class="citizen-status-grid"><div><section class="card"><div class="card-body"><span class="citizen-eyebrow">TU DOCUMENTO</span><h2 class="h4 mt-3">{{ ucfirst(strtolower($tramite->datos['busqueda']['tipo'] ?? $tramite->acta?->tipo ?? 'Acta')) }}</h2><p class="text-muted">{{ $tramite->datos['busqueda']['titular'] ?? '' }}</p><span class="citizen-status-label">{{ App\Models\TramitePublico::ESTADOS[$tramite->estado] }}</span><hr class="my-4"><p><strong>Relación declarada</strong><br>{{ App\Models\TramitePublico::RELACIONES[$tramite->datos['relacion'] ?? ''] ?? 'No informada' }}</p><p><strong>Motivo</strong><br>{{ $tramite->datos['motivo'] ?? 'No informado' }}</p><p class="small text-muted mb-0">El personal municipal revisará si corresponde autorizar la entrega y podrá pedir sustento adicional.</p></div></section>
<section class="card"><div class="card-body"><span class="citizen-eyebrow">HISTORIAL DE ATENCIÓN</span><h2 class="h5 mt-3 mb-4">Últimos movimientos</h2><div class="citizen-timeline">@forelse($tramite->eventos->reverse() as $evento)<div><strong>{{ App\Models\TramitePublico::ESTADOS[$evento->estado] ?? $evento->estado }}</strong><p class="small mb-2">{{ $evento->created_at->format('d/m/Y · H:i') }}</p><p>{{ $evento->mensaje }}</p></div>@empty<p class="text-muted small">{{ $tramite->pago?->estado === 'PAGADO' ? 'Tu pago está confirmado. Aquí aparecerán las respuestas de Registro Civil.' : 'Aquí verás los avances después de confirmar tu pago.' }}</p>@endforelse</div></div></section></div>
<aside class="card"><div class="card-body">
@if($porPagar)
<span class="citizen-eyebrow">PAGO DEL TRÁMITE</span>
<p class="mt-2 mb-0">Tu pago está pendiente. <a href="#pagar">Paga arriba</a> para que Registro Civil revise tu solicitud.</p>
@else
@include('portal.pago')
@unless($eligeEntrega)@include('portal.consulta-whatsapp')@endunless
@endif
</div></aside></div>
@endsection
