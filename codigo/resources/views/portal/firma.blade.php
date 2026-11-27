@php($entrega = $tramite->datos['entrega'] ?? [])
@php($firmado = !empty($entrega['firmado_ruta']))
@php($paso = $tramite->estado === 'LISTA' ? 3 : ($firmado ? 2 : 1))
<ol class="list-unstyled d-flex gap-2 small mb-4" aria-label="Progreso de la entrega">
@foreach([1 => 'Firmar', 2 => 'Publicar', 3 => 'Entregado'] as $n => $nombre)
  @php($hecho = $paso > $n || $paso === 3)
  <li class="flex-fill text-center py-2 rounded-2 {{ $hecho ? 'bg-success text-white' : ($paso === $n ? 'border border-success text-success fw-semibold' : 'bg-light text-muted') }}" @if($paso === $n && ! $hecho) aria-current="step" @endif>
    @if($hecho)<i class="bi bi-check-lg me-1" aria-hidden="true"></i>@endif{{ $nombre }}
  </li>
@endforeach
</ol>

@if($tramite->estado === 'LISTA')
<div class="alert alert-success d-flex gap-2"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><div><strong>Documento entregado</strong><br><span class="small">El ciudadano ya puede descargarlo desde su seguimiento.</span></div></div>
<a class="btn btn-outline-success w-100" href="{{ route('entrega.archivo', [$tramite, 'firmado']) }}" target="_blank" rel="noopener">Ver documento publicado ↗</a>
@include('portal.canales-entrega')

@elseif(! $firmado)
@php($acta = $tramite->acta)
@php($reusable = (bool) $acta?->copiaFirmadaVigente())
@if($reusable)
<div class="border border-success rounded-3 p-3 mb-3" style="background:#e9f7ef">
  <strong class="d-block"><i class="bi bi-patch-check-fill text-success me-1" aria-hidden="true"></i>Esta acta ya tiene una copia firmada</strong>
  <p class="small mb-2">Firmada por <strong>{{ $acta->firmado_firmante ?? 'el funcionario' }}</strong> el {{ $acta->firmado_at->format('d/m/Y') }}@if($acta->firmado_tramite_id) (solicitud N.º {{ str_pad((string) $acta->firmado_tramite_id, 6, '0', STR_PAD_LEFT) }})@endif.@if($vence = $acta->copiaFirmadaVenceEl()) Se puede volver a entregar hasta el {{ $vence->format('d/m/Y') }}.@endif</p>
  <form method="POST" action="{{ route('entrega.reutilizar', $tramite) }}" data-confirm="¿Entregar la copia ya firmada? El ciudadano podrá descargarla y recibirá el aviso.">@csrf
    <input type="hidden" name="version" value="{{ $tramite->version }}">
    <button class="btn btn-success btn-lg w-100"><i class="bi bi-send-check me-2" aria-hidden="true"></i>Entregar con la copia ya firmada</button>
  </form>
  <a class="small d-inline-block mt-2" href="{{ route('actas.pdf-firmado', $acta) }}" target="_blank" rel="noopener">Ver copia firmada ↗</a>
  <p class="review-muted small mt-1 mb-0">Se vuelve a comprobar la firma y que el acta no haya cambiado. No necesitas Firma Perú.</p>
</div>
<details class="mb-2"><summary class="small review-muted">Prefiero firmar una copia nueva</summary><div class="mt-3">
@endif
<div class="manual-sign">
  <div class="d-flex gap-3 mb-3">
    <span class="badge rounded-circle bg-success d-flex align-items-center justify-content-center flex-shrink-0" style="width:2rem;height:2rem">1</span>
    <div class="flex-grow-1">
      <strong class="d-block mb-2">Descarga el acta</strong>
      <a class="btn btn-success w-100" href="{{ route('entrega.archivo', [$tramite, 'original']).'?descargar=1' }}"><i class="bi bi-download me-2" aria-hidden="true"></i>Descargar para firmar</a>
    </div>
  </div>
  <div class="d-flex gap-3 mb-3">
    <span class="badge rounded-circle bg-success d-flex align-items-center justify-content-center flex-shrink-0" style="width:2rem;height:2rem">2</span>
    <div class="flex-grow-1">
      <strong class="d-block mb-2">Fírmala con tu certificado</strong>
      <a class="btn btn-outline-success w-100" href="rcmunicipal-firma://abrir"><i class="bi bi-pen me-2" aria-hidden="true"></i>Abrir Firma Perú</a>
      <p class="review-muted small mt-1 mb-0">Si no se abre, búscalo en el menú Inicio de Windows. Guarda el PDF firmado.</p>
    </div>
  </div>
  <div class="d-flex gap-3">
    <span class="badge rounded-circle bg-success d-flex align-items-center justify-content-center flex-shrink-0" style="width:2rem;height:2rem">3</span>
    <form class="flex-grow-1" method="POST" enctype="multipart/form-data" action="{{ route('entrega.cargar', $tramite) }}" data-auto-upload>@csrf
      <input type="hidden" name="version" value="{{ $tramite->version }}">
      <strong class="d-block mb-2">Sube el PDF firmado</strong>
      <label for="archivo_firmado" class="d-block text-center p-4 rounded-3 mb-0" style="border:2px dashed #2f6b4f;cursor:pointer" data-dropzone>
        <i class="bi bi-cloud-arrow-up fs-2 d-block text-success" aria-hidden="true"></i>
        <span data-upload-label>Arrastra aquí el PDF firmado o <u>haz clic para elegirlo</u></span>
        <small class="d-block review-muted mt-1">Se comprueba la firma automáticamente · máx. 20 MB</small>
      </label>
      <input id="archivo_firmado" name="archivo" type="file" accept="application/pdf" class="visually-hidden" required>
      <noscript><button class="btn btn-success w-100 mt-2">Comprobar y guardar</button></noscript>
    </form>
  </div>
</div>
@if($reusable)</div></details>@endif

@else
<h3 class="h5">Revisa y publica</h3>
<div class="alert alert-success small d-flex gap-2"><i class="bi bi-patch-check-fill" aria-hidden="true"></i><div>Firma válida e íntegra.<br>Firmante: <strong>{{ $entrega['firmante'] }}</strong></div></div>
<a class="btn btn-outline-success w-100 mb-3" href="{{ route('entrega.archivo', [$tramite, 'firmado']) }}" target="_blank" rel="noopener"><i class="bi bi-eye me-2" aria-hidden="true"></i>Ver PDF firmado ↗</a>
<form method="POST" action="{{ route('entrega.publicar', $tramite) }}" data-confirm="¿Publicar el documento? El ciudadano podrá descargarlo y recibirá el aviso.">@csrf
  <input type="hidden" name="version" value="{{ $tramite->version }}">
  <div class="form-check mb-3"><input class="form-check-input" id="revision_firma" name="revision_firma" type="checkbox" value="1" required><label class="form-check-label" for="revision_firma">Revisé el contenido y el certificado del firmante.</label></div>
  <details class="mb-3"><summary class="small review-muted">Constancia de revisión (ya redactada, puedes editarla)</summary>
    <textarea id="sustento_firma" name="sustento_firma" class="form-control mt-2" rows="3" minlength="15" maxlength="1500" required>{{ old('sustento_firma', 'Se revisó el contenido del acta y la firma digital de '.$entrega['firmante'].' el '.now()->format('d/m/Y').'; certificado vigente y firmante autorizado.') }}</textarea>
  </details>
  <button class="btn btn-success btn-lg w-100"><i class="bi bi-send-check me-2" aria-hidden="true"></i>Publicar y entregar</button>
  @if(($entrega['preparado_ruta'] ?? null) === $tramite->acta?->pdf_ruta)<p class="review-muted small mt-2 mb-0"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Esta copia quedará guardada en el acta: las próximas solicitudes se entregan sin volver a firmar.</p>@endif
  <p class="review-muted small mt-2 mb-0">Se avisará al ciudadano{{ !empty($tramite->datos['canales_entrega']['correo']) ? ' y se le enviará el PDF por correo' : '' }} automáticamente.</p>
</form>
<details class="review-details mt-3"><summary>Subí un archivo equivocado</summary><form class="mt-3" method="POST" action="{{ route('entrega.descartar', $tramite) }}" data-confirm="¿Retirar este PDF? Tendrás que subir el correcto.">@csrf<input type="hidden" name="version" value="{{ $tramite->version }}"><label for="motivo_descarte" class="form-label">Motivo</label><textarea id="motivo_descarte" name="motivo_descarte" minlength="10" maxlength="500" class="form-control mb-3" required placeholder="Ej.: se subió el PDF sin firmar"></textarea><button class="btn btn-outline-danger w-100">Retirar y subir otro</button></form></details>
@include('portal.canales-entrega')
@endif

@once
@push('scripts')
<script>
(() => {
    // Upload as soon as a PDF is chosen or dropped: no second button to press.
    const form = document.querySelector('[data-auto-upload]');
    if (!form) return;
    const input = form.querySelector('input[type=file]');
    const zone = form.querySelector('[data-dropzone]');
    const label = form.querySelector('[data-upload-label]');
    const enviar = () => {
        const archivo = input.files[0];
        if (!archivo) return;
        if (archivo.type && archivo.type !== 'application/pdf') { label.textContent = 'El archivo debe ser un PDF.'; return; }
        label.textContent = 'Comprobando la firma de «' + archivo.name + '»…';
        zone.style.opacity = '.6';
        form.submit();
    };
    input.addEventListener('change', enviar);
    ['dragover', 'dragenter'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.style.background = '#e9f7ef'; }));
    ['dragleave', 'drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.style.background = ''; }));
    zone.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) { input.files = ev.dataTransfer.files; enviar(); } });
})();
</script>
@endpush
@endonce
