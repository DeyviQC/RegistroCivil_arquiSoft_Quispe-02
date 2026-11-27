@extends('portal.layout')
@section('titulo', 'Mis solicitudes')
@section('contenido')
<div class="citizen-banner"><span class="citizen-eyebrow">TU ESPACIO DE CONSULTA</span><h1>Mis solicitudes</h1><p>Selecciona una solicitud para ver su estado y los documentos disponibles.</p></div>
<p class="client-access-note"><i class="bi bi-clock me-2" aria-hidden="true"></i>Tu acceso dura 30 minutos desde la verificación.</p>
@foreach($tramites as $tramite)
<article class="card mb-3"><div class="card-body p-4">
<h2 class="h5">{{ ucfirst(strtolower($tramite->datos['busqueda']['tipo'] ?? 'Acta')) }} · {{ $tramite->datos['busqueda']['titular'] ?? 'Solicitud de acta' }}</h2>
<p>Solicitud N.º {{ str_pad((string) $tramite->id, 6, '0', STR_PAD_LEFT) }} · {{ $tramite->created_at->format('d/m/Y H:i') }}</p>
<p><span class="citizen-status-label">{{ App\Models\TramitePublico::ESTADOS[$tramite->estado] ?? $tramite->estado }}</span></p>
<form method="POST" action="{{ route('portal.seleccionar') }}">@csrf<input type="hidden" name="solicitud" value="{{ $tramite->id }}"><button class="btn btn-primary">Ver detalle del trámite</button></form>
</div></article>
@endforeach
@endsection
