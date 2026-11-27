{{-- Payment block: confirmed state, pending attempts, or the Yape/card choice. --}}
<span class="citizen-eyebrow">PAGO DEL TRÁMITE</span><div class="citizen-price">S/ {{ number_format(config('portal.tarifa_centimos') / 100, 2) }} <small>por acta</small></div>
@if($tramite->pago?->estado === 'PAGADO')<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><strong>Pago confirmado</strong><p class="mb-0 mt-2">{{ $tramite->estado === 'PENDIENTE' ? 'Tu solicitud está pendiente de atención por el Registro Civil.' : 'Revisa el historial para conocer el avance de tu solicitud.' }}</p></div>
@elseif($tramite->estado === 'VENCIDA')<div class="alert alert-warning">Esta solicitud venció porque no se inició el pago a tiempo. No se realizó ningún cobro. Puedes iniciar una nueva búsqueda cuando lo necesites.</div>
@elseif($tramite->pago?->yape_intento)<div class="alert alert-info small">Tienes un pago con Yape en proceso. No pagues otra vez: comprueba su resultado.</div><a href="{{ route('yape.formulario') }}" class="btn btn-primary w-100">Comprobar mi pago con Yape</a>
@elseif($tramite->pago?->estado === 'VENCIDO')<div class="alert alert-warning">La orden de prueba venció. Consulta al personal antes de realizar otro pago.</div>
@elseif($tramite->pago?->qr && $tramite->pago->expira_at?->isFuture())<h3 class="h6">Escanea con Yape · Pago de prueba</h3><img src="{{ $tramite->pago->qr }}" alt="QR de pago para esta solicitud" style="max-width:280px;width:100%"><p class="small">Vence: {{ $tramite->pago->expira_at->format('d/m/Y H:i') }}</p><a href="{{ route('portal.estado') }}" class="btn btn-primary w-100">Comprobar confirmación</a>
@elseif($tramite->pago?->proveedor_id)<div class="alert alert-warning">El sistema espera la confirmación del proveedor. No realices otro pago.</div>
@elseif($tramite->pago && $tramite->pago->proveedor !== 'MERCADOPAGO')<div class="alert alert-warning">Esta solicitud tiene una orden con la pasarela anterior. Contacta a Registro Civil para revisar su estado antes de pagar nuevamente.</div>
@else
@php $checkoutAbierto = (bool) $tramite->pago?->mercadopago_preferencia_id; @endphp
<p class="h5 fw-bold mb-1">{{ $checkoutAbierto ? 'Completa tu pago con tarjeta' : 'Paga fácil' }}</p>
<p class="small text-muted">{{ $checkoutAbierto ? 'Ya abriste el pago con tarjeta. Si lo completaste, comprueba el resultado.' : 'Paga en segundos con Yape desde tu celular. También aceptamos tarjeta.' }}</p>
@unless($checkoutAbierto)
<a href="{{ route('yape.formulario') }}" class="btn btn-primary w-100 text-start d-flex align-items-center gap-3 py-3 mb-2 position-relative"><i class="bi bi-phone fs-3" aria-hidden="true"></i><span class="flex-grow-1"><strong class="d-block fs-5">Pagar con Yape</strong><small class="fw-normal">Rápido y sin tarjeta</small></span><span class="badge rounded-pill bg-warning text-dark">Más usado</span></a>
<p class="small text-muted text-center my-2">o</p>
@endunless
<form method="POST" action="{{ route('mercadopago.pagar') }}">@csrf<button class="btn {{ $checkoutAbierto ? 'btn-primary' : 'btn-outline-primary' }} w-100 text-start d-flex align-items-center gap-3 {{ $checkoutAbierto ? 'py-3' : 'py-2' }}"><i class="bi bi-credit-card fs-4" aria-hidden="true"></i><span><strong class="d-block">{{ $checkoutAbierto ? 'Volver al pago con tarjeta' : 'Pagar con tarjeta' }}</strong><small class="fw-normal">Débito o crédito, en la página segura de Mercado Pago</small></span></button></form>
@if($checkoutAbierto)<form method="POST" action="{{ route('mercadopago.comprobar') }}" class="mt-2">@csrf<button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>Ya pagué, comprobar pago</button></form>@endif
<p class="small text-muted mt-3 mb-0">Modo de prueba · No se cobra dinero real.</p>
@endif
