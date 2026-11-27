@php($firmaPeruDisponible = app(\App\Services\FirmaPeruService::class)->disponible())
<div class="p-3 rounded-3 border bg-light">
<h3 class="h5"><i class="bi bi-fingerprint me-2"></i>Firmar desde el sistema</h3>
<p class="review-muted">Firma Perú coloca el sello con tu certificado en la misma página y devuelve el PDF firmado automáticamente. No se añade el recuadro de preparación.</p>
@if($firmaPeruDisponible)
<p class="small">Usa Microsoft Edge y mantén conectado tu DNIe. Se abrirá el firmador para elegir la ubicación de la firma e ingresar tu PIN.</p>
<button type="button" id="firma-peru-iniciar" class="btn btn-success w-100" data-url="{{ route('firmaperu.iniciar', $tramite) }}" data-cancelar="{{ route('firmaperu.cancelar', $tramite) }}" data-version="{{ $tramite->version }}" data-csrf="{{ csrf_token() }}">Firmar con DNIe · Firma Perú</button>
<button type="button" id="firma-peru-cancelar" class="btn btn-outline-secondary w-100 mt-2" hidden>Cancelar intento de firma</button>
<p id="firma-peru-estado" class="small mt-3 mb-0" role="status" aria-live="polite"></p>
<div id="addComponent" style="display:none;"></div>
@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
<script>var jqFirmaPeru = window.jQuery ? window.jQuery.noConflict(true) : null;</script>
<script src="{{ asset('js/firma-peru.js') }}?v={{ filemtime(public_path('js/firma-peru.js')) }}"></script>
<script src="https://apps.firmaperu.gob.pe/web/clienteweb/firmaperu.min.js"></script>
@endpush
@else
<div class="alert alert-warning mb-0"><strong>Pendiente de habilitación institucional</strong><br>Para activar la firma desde esta web falta la credencial de Firma Perú entregada por PCM. El programa instalado en tu equipo puede seguir utilizándose con la alternativa manual.</div>
@endif
</div>
