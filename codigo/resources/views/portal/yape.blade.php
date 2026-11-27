@extends('portal.layout')
@section('titulo', 'Pagar con Yape')
@section('contenido')
<div class="citizen-banner"><span class="citizen-eyebrow">PAGO DEL TRÁMITE</span><h1>Pagar con Yape</h1><p>Ingresa tu celular y el código de aprobación de tu app Yape.</p></div>
<section class="card mx-auto" style="max-width:520px"><div class="card-body p-4">
<div class="d-flex justify-content-between align-items-baseline mb-3">
  <span class="text-muted">Copia de acta</span>
  <span class="h3 mb-0 fw-bold">S/ {{ number_format(config('portal.tarifa_centimos') / 100, 2) }}</span>
</div>
@if($tramite->pago?->estado === 'PAGADO')
<div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>Tu pago ya está confirmado.</div>
@elseif($tramite->pago?->mercadopago_preferencia_id || ($tramite->pago && $tramite->pago->proveedor !== 'MERCADOPAGO'))
<div class="alert alert-warning">Esta solicitud ya tiene un pago iniciado con tarjeta. Termínalo desde tu solicitud.</div>
@else
@unless($disponible)<div class="alert alert-warning">El pago con Yape aún no está configurado.</div>@endunless
<form method="POST" action="{{ route('yape.pagar') }}" autocomplete="off" novalidate>@csrf
  <label for="celular" class="form-label fw-semibold">Número de celular</label>
  <div class="input-group input-group-lg mb-3">
    <span class="input-group-text"><i class="bi bi-phone" aria-hidden="true"></i></span>
    <input id="celular" name="celular" type="tel" inputmode="numeric" maxlength="9" pattern="[0-9]{9}" required
      class="form-control @error('celular') is-invalid @enderror" placeholder="9XX XXX XXX" value="{{ old('celular') }}" autofocus>
  </div>
  <label for="otp" class="form-label fw-semibold">Código de aprobación</label>
  <input id="otp" name="otp" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required
    class="form-control form-control-lg text-center fw-bold @error('otp') is-invalid @enderror" style="letter-spacing:.5em" placeholder="••••••">
  <p class="small text-muted mt-2"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Lo encuentras en tu app Yape, en la opción <strong>Código de aprobación</strong>. Cambia cada pocos segundos.</p>
  <button class="btn btn-primary btn-lg w-100 mt-2" @disabled(!$disponible)>
    {{ $tramite->pago?->yape_intento ? 'Comprobar mi pago' : 'Pagar S/ '.number_format(config('portal.tarifa_centimos') / 100, 2) }}
  </button>
</form>
<div class="border rounded p-3 mt-4 small bg-light">
  <strong><i class="bi bi-cone-striped me-1" aria-hidden="true"></i>Modo de prueba · no se cobra dinero real</strong>
  <p class="mb-1 mt-2">Usa estos datos ficticios de Mercado Pago:</p>
  <ul class="mb-0">
    <li>Pago aprobado: celular <code>111111111</code> y código <code>123456</code></li>
    <li>Pago rechazado: celular <code>111111114</code> · Saldo insuficiente: <code>111111113</code></li>
  </ul>
</div>
@endif
<a href="{{ route('portal.estado') }}" class="d-inline-block mt-4"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a mi solicitud</a>
</div></section>
@endsection
