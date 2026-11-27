@php($canales = $tramite->datos['canales_entrega'] ?? [])
@php($puedeEnviar = $tramite->estado === 'LISTA')
<section class="mt-4 pt-3 border-top" aria-labelledby="titulo-enviar-documento">
<h3 id="titulo-enviar-documento">Preferencias y envío</h3>
<p class="review-muted small">Medios elegidos por el ciudadano para recibir su acta.</p>
@if(!empty($canales['correo']))
<div class="p-3 mb-3 border rounded-3"><h4 class="h6"><i class="bi bi-envelope me-2" aria-hidden="true"></i>Correo electrónico</h4>
<p class="review-muted mb-2 text-break">{{ $tramite->email }}</p>
@if(!empty($entrega['correo_enviado_at']))
<p class="text-success small">Correo enviado el {{ \Illuminate\Support\Carbon::parse($entrega['correo_enviado_at'])->format('d/m/Y H:i') }}.</p>
@elseif($puedeEnviar)
<form method="POST" action="{{ route('entrega.correo', $tramite) }}" class="mb-3">@csrf<button class="btn btn-success w-100"><i class="bi bi-envelope me-2" aria-hidden="true"></i>Enviar PDF por correo</button></form>
@else
<button class="btn btn-outline-secondary w-100" disabled>Enviar por correo al publicar</button>
@endif
<p class="review-muted small mb-0">Se envía el PDF firmado como adjunto al correo verificado, incluido Gmail.</p></div>
@endif
@if(!empty($canales['whatsapp']))
@php($numeroValido = preg_match('/^[1-9][0-9]{7,14}$/D', $canales['numero'] ?? ''))
<div class="p-3 mb-3 border rounded-3"><h4 class="h6"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>WhatsApp</h4>
<p class="review-muted small mb-2">
  @if(!empty($canales['whatsapp_iniciado_at']))
    El ciudadano eligió WhatsApp el {{ \Illuminate\Support\Carbon::parse($canales['whatsapp_iniciado_at'])->format('d/m/Y H:i') }} y escribe desde su celular mencionando la <strong>solicitud N.º {{ str_pad((string) $tramite->id, 6, '0', STR_PAD_LEFT) }}</strong>.
  @else
    El ciudadano pidió recibir su documento por WhatsApp.
  @endif
  Responde en esa conversación: la municipalidad no inicia chats.
</p>
@if($numeroValido)<p class="review-muted mb-2">+{{ $canales['numero'] }}</p>@endif
@if($puedeEnviar)
@php($mensajeListo = 'Hola, le saluda Registro Civil de la Municipalidad Distrital Andrés Avelino Cáceres Dorregaray. Su acta de la solicitud N.º '.str_pad((string) $tramite->id, 6, '0', STR_PAD_LEFT).' ya está lista. Le adjuntamos el documento firmado digitalmente en PDF. También puede descargarlo desde el seguimiento de su trámite. ¡Gracias!')
<ol class="small ps-3 mb-2">
  <li class="mb-2"><a href="{{ route('entrega.archivo', [$tramite, 'firmado']).'?descargar=1' }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1" aria-hidden="true"></i>Descargar PDF firmado para adjuntar</a></li>
  <li class="mb-2">
    @if($numeroValido)
    <a href="https://wa.me/{{ $canales['numero'] }}?text={{ rawurlencode($mensajeListo) }}" class="btn btn-sm btn-success" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Abrir chat con el mensaje listo</a>
    @else
    Abre su chat en WhatsApp (búscalo por «solicitud N.º {{ str_pad((string) $tramite->id, 6, '0', STR_PAD_LEFT) }}») y pega el mensaje:
    @endif
  </li>
  <li>Adjunta el PDF y pulsa <strong>Enviar</strong>.</li>
</ol>
<div class="border rounded-2 p-2 small bg-light mb-2" data-mensaje-whatsapp>{{ $mensajeListo }}</div>
<button type="button" class="btn btn-sm btn-outline-secondary w-100" data-copiar-mensaje><i class="bi bi-clipboard me-1" aria-hidden="true"></i>Copiar mensaje</button>
<script>
document.querySelectorAll('[data-copiar-mensaje]').forEach(b => b.addEventListener('click', async () => {
    const texto = b.previousElementSibling.textContent.trim();
    try { await navigator.clipboard.writeText(texto); b.innerHTML = '<i class="bi bi-check2 me-1"></i>Mensaje copiado'; }
    catch (e) { b.textContent = 'Selecciona el texto y cópialo (Ctrl+C)'; }
}));
</script>
@else
<p class="review-muted small mb-0">Cuando publiques el PDF firmado podrás descargarlo aquí para enviarlo en ese chat. Mientras tanto, responde sus consultas con el estado del trámite.</p>
@endif
</div>
@endif
@if(empty($canales['correo']) && empty($canales['whatsapp']))<p class="review-muted">El ciudadano aún no ha solicitado entrega por correo o WhatsApp. Puede descargar el documento desde su seguimiento.</p>@endif
@unless($puedeEnviar)<p class="review-muted small">Al publicar, el correo se envía automáticamente y el ciudadano recibe el aviso.</p>@endunless
</section>
