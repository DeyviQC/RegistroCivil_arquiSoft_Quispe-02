@php($enlaceWhatsApp = app(\App\Services\WhatsAppConsulta::class)->enlace($tramite))
@if($tramite->pago?->estado === 'PAGADO')
@php($canales = $tramite->datos['canales_entrega'] ?? [])
<section class="mt-4 pt-4 border-top" aria-labelledby="titulo-consulta-whatsapp">
<h3 id="titulo-consulta-whatsapp" class="h5 fw-bold mb-3">¿Cómo deseas recibir tu documento?</h3>

@if($enlaceWhatsApp)
<div class="rounded-3 p-3 mb-3" style="background:#e9f7ef;border:2px solid #25d366">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <strong class="fs-5"><i class="bi bi-whatsapp me-2" style="color:#128c7e" aria-hidden="true"></i>Por WhatsApp</strong>
    <span class="badge rounded-pill" style="background:#128c7e">Recomendado</span>
  </div>
  <p class="small mb-3">Escríbenos y te enviaremos los <strong>avisos</strong> de tu trámite y tu <strong>acta firmada</strong> en ese mismo chat.</p>
  <form method="POST" action="{{ route('portal.whatsapp') }}">@csrf
    <button class="btn btn-lg w-100 text-white fw-semibold" style="background:#25d366;border-color:#25d366"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>Escribir a Registro Civil<span class="visually-hidden"> (abre WhatsApp)</span></button>
  </form>
  <p class="small text-muted mt-2 mb-0">
    @if(!empty($canales['whatsapp_iniciado_at']))
      <i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i>Elegiste WhatsApp. Si aún no enviaste el mensaje, vuelve a pulsar el botón.
    @else
      Se abrirá WhatsApp con un mensaje listo. Solo pulsa <strong>Enviar</strong>.
    @endif
  </p>
</div>
<p class="small text-muted text-center my-2">o también</p>
@endif

<form method="POST" action="{{ route('portal.canales') }}" class="border rounded-3 p-3">@csrf
  <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="correo" id="entrega-correo" value="1" @checked(old('correo', $canales['correo'] ?? false))><label class="form-check-label" for="entrega-correo"><i class="bi bi-envelope me-1" aria-hidden="true"></i>Recibir por correo verificado</label></div>
  <p class="small text-muted mb-2">Siempre podrás descargarlo aquí cuando esté listo.</p>
  <button class="btn btn-outline-primary w-100">Guardar</button>
</form>
</section>
@endif
