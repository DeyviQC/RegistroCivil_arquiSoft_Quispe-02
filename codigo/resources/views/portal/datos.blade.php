@extends('portal.layout')
@section('titulo', 'Completa tu solicitud')
@section('contenido')
<div class="citizen-banner"><span class="citizen-eyebrow">ACTA LOCALIZADA · NUEVA SOLICITUD</span><h1>Completa tu solicitud</h1><p>Primero indica quién solicita el acta. Luego completa tus datos y el correo donde recibirás tu código.</p></div>
<div class="wizard-shell"><aside class="wizard-aside"><span class="citizen-eyebrow">RESUMEN DE SOLICITUD</span><h2>Tu acta seleccionada</h2><p>Confirma el documento y completa tus datos.</p><div class="wizard-summary"><small>ACTA SELECCIONADA</small><strong>{{ ucfirst(strtolower($busqueda['datos']['tipo'])) }}</strong><p>{{ $busqueda['datos']['titular'] }}</p><dl class="wizard-record-date"><dt>Fecha del hecho</dt><dd>{{ \Carbon\Carbon::parse($busqueda['datos']['fecha'])->format('d/m/Y') }}</dd></dl><span><i class="bi bi-check-circle-fill"></i> Localizada en el archivo digital</span></div><div class="wizard-next-steps"><h3>Después de completar tus datos</h3><ol><li><strong>Verifica tu correo</strong><span>Ingresa el código que te enviaremos.</span></li><li><strong>Continúa al pago</strong><span>Revisa el importe antes de confirmar.</span></li><li><strong>Revisión municipal</strong><span>Registro Civil evaluará la solicitud antes de entregar el acta.</span></li></ol></div><div class="wizard-privacy"><i class="bi bi-shield-lock"></i><span>Tu correo se verificará antes de continuar. Sin crear una cuenta.</span></div></aside><div class="wizard-content"><nav class="wizard-navigation" aria-label="Pasos del formulario" hidden><div class="wizard-step-buttons"><button type="button" data-wizard-step="0"><span>1</span>El acta</button><button type="button" data-wizard-step="1"><span>2</span>Tus datos</button><button type="button" data-wizard-step="2"><span>3</span>Contacto</button></div><div class="wizard-progress-track"><span id="wizard-progress"></span></div><p id="wizard-caption" aria-live="polite">Paso 1 de 3 · El acta y tu relación</p></nav><form method="POST" action="{{ route('portal.registrar') }}" data-tramite-wizard>@csrf
<section class="card applicant-section" aria-labelledby="acta-heading"><div class="card-body">
<div class="applicant-heading"><span>1</span><div><small>EL DOCUMENTO</small><h2 id="acta-heading">¿Para quién es el acta?</h2></div></div>
<div class="applicant-record"><span class="citizen-eyebrow">{{ ucfirst(strtolower($busqueda['datos']['tipo'])) }}</span><h3>{{ $busqueda['datos']['titular'] }}</h3><p>{{ $busqueda['datos']['tipo'] === 'NACIMIENTO' ? 'Fecha de nacimiento' : 'Fecha del hecho' }}: <strong>{{ \Carbon\Carbon::parse($busqueda['datos']['fecha'])->format('d/m/Y') }}</strong></p></div>
<label for="relacion" class="form-label">¿Quién realiza la solicitud?</label><select id="relacion" name="relacion" class="form-select" aria-describedby="relacion-ayuda" required><option value="">Selecciona una opción</option>@foreach(App\Models\TramitePublico::RELACIONES as $clave => $nombre)<option value="{{ $clave }}" @selected(old('relacion') === $clave)>{{ $nombre }}</option>@endforeach</select>
<p id="relacion-ayuda" class="applicant-help" aria-live="polite">El titular es la persona cuyo nombre aparece en el acta.</p>
<div id="campo-dni-titular" class="mt-3"><label for="dni_titular" id="dni-titular-label" class="form-label">DNI de la persona del acta (opcional)</label><input id="dni_titular" name="dni_titular" class="form-control" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" placeholder="Solo si lo conoces" value="{{ old('dni_titular') }}"><p class="applicant-help">Si no lo conoces o no está consignado, déjalo vacío.</p></div>
</div></section>
<section id="seccion-solicitante" class="card applicant-section" aria-labelledby="solicitante-heading"><div class="card-body">
<div class="applicant-heading"><span>2</span><div><small>QUIÉN SOLICITA</small><h2 id="solicitante-heading">Tus datos personales</h2></div></div>
<p id="solicitante-ayuda" class="applicant-intro">Ingresa los datos de la persona que realiza el trámite.</p>
<div id="resumen-titular" class="applicant-prefilled" hidden><i class="bi bi-check-circle"></i><div><strong>Estos datos ya están completos</strong><p>{{ $autocompletar['nombre'] }}@if($autocompletar['nacimiento']) · {{ \Carbon\Carbon::parse($autocompletar['nacimiento'])->format('d/m/Y') }}@endif</p></div></div>
<div class="row"><div class="col-md-8 mb-3"><label for="dni" id="dni-label" class="form-label">Tu DNI</label><input id="dni" name="dni" class="form-control" pattern="[0-9]{8}" maxlength="8" inputmode="numeric" placeholder="8 dígitos" value="{{ old('dni') }}" required></div><div class="col-md-4 mb-3"><label for="verificador" class="form-label">Dígito verificador</label><input id="verificador" name="verificador" class="form-control" pattern="[0-9]" maxlength="1" inputmode="numeric" placeholder="1 dígito" aria-describedby="verificador-ayuda" value="{{ old('verificador') }}" required></div></div>
<p id="verificador-ayuda" class="applicant-help">El dígito verificador es el número que acompaña a tu número de DNI en el documento.</p>
<div id="campo-nombre" class="mb-3"><label for="nombre" class="form-label">Tus nombres y apellidos completos</label><input id="nombre" name="nombre" class="form-control" maxlength="200" value="{{ old('nombre') }}" autocomplete="name" required></div>
<div id="campo-nacimiento" class="mb-3"><label for="nacimiento" class="form-label">Tu fecha de nacimiento</label><input id="nacimiento" type="date" name="nacimiento" class="form-control" value="{{ old('nacimiento') }}" max="{{ now()->toDateString() }}" required></div>
<details class="applicant-optional" @if(old('ubigeo')) open @endif><summary>Agregar ubigeo del DNI (opcional)</summary><label for="ubigeo" class="form-label mt-3">Ubigeo que figura en tu DNI</label><input id="ubigeo" name="ubigeo" class="form-control" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" placeholder="6 dígitos" value="{{ old('ubigeo') }}"></details>
<details class="applicant-optional"><summary>¿Cómo se revisan estos datos?</summary><p>Consultaremos los datos disponibles del DNI. La fecha de nacimiento, el dígito verificador y el ubigeo se registran como declarados cuando el proveedor no permite contrastarlos. El personal revisa la solicitud antes de autorizar la entrega.</p></details>
</div></section>
<section id="seccion-contacto" class="card applicant-section" aria-labelledby="contacto-heading"><div class="card-body">
<div class="applicant-heading"><span>3</span><div><small>CÓMO CONTACTARTE</small><h2 id="contacto-heading">Tu correo y el motivo del trámite</h2></div></div>
<div class="contact-review-summary" id="contact-review-summary" hidden><div><small>SOLICITANTE</small><strong id="contact-review-name"></strong><span id="contact-review-relation"></span></div><button type="button" id="wizard-back-contact" class="contact-review-edit">Revisar mis datos <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button></div><p class="contact-step-intro">Indica un correo al que tengas acceso y el uso que darás al acta.</p><div class="mb-4"><label for="email" class="form-label">Tu correo electrónico</label><input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="tu.correo@ejemplo.com" autocomplete="email" aria-describedby="email-ayuda" required><p id="email-ayuda" class="applicant-help"><i class="bi bi-envelope me-1"></i>Aquí recibirás el código para continuar y consultar tu solicitud.</p></div>
<div class="mb-3"><label for="motivo" class="form-label">¿Para qué necesitas el acta?</label><textarea id="motivo" name="motivo" class="form-control" rows="3" maxlength="1500" placeholder="Por ejemplo: matrícula, estudios u otro trámite." required>{{ old('motivo') }}</textarea></div>
<details class="applicant-optional" @if(old('whatsapp')) open @endif><summary>Agregar WhatsApp (opcional)</summary><label for="whatsapp" class="form-label mt-3">Tu número con código de país</label><input id="whatsapp" name="whatsapp" class="form-control" placeholder="+51987654321" value="{{ old('whatsapp') }}" autocomplete="tel"><p class="applicant-help">Después del pago podrás solicitar la entrega por WhatsApp. El código de acceso siempre llegará a tu correo.</p></details>
<div class="applicant-review-note"><i class="bi bi-info-circle"></i><p>La municipalidad revisará tu solicitud y podrá pedir sustento. El pago no garantiza la autorización ni la entrega del acta.</p></div>
<div class="form-check mb-4"><input id="declaracion" type="checkbox" name="declaracion" value="1" class="form-check-input" required><label id="texto-declaracion" for="declaracion" class="form-check-label">Selecciona quién realiza la solicitud para leer la declaración.</label></div>
<button class="btn btn-primary w-100">Enviar código a mi correo <i class="bi bi-arrow-right ms-2"></i></button><p class="text-center applicant-help mt-3 mb-0">En este paso no se realiza ningún cobro.</p>
</div></section><div id="wizard-controls" class="wizard-controls" hidden><button type="button" id="wizard-previous" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Atrás</button><button type="button" id="wizard-next" class="btn btn-primary">Continuar <i class="bi bi-arrow-right ms-2"></i></button></div></form></div></div>
<script>
(function(){
    const datos = @json($autocompletar);
    const textos = @json(App\Services\DeclaracionSolicitud::textos());
    const relacion = document.getElementById('relacion');
    const nombre = document.getElementById('nombre');
    const nacimiento = document.getElementById('nacimiento');
    const dniTitular = document.getElementById('dni_titular');
    let propios = {nombre:nombre.value, nacimiento:nacimiento.value};
    let eraTitular = false;
    const roles = {madre:'de la madre', padre:'del padre', representante:'del representante', otro:'de quien solicita'};
    function adaptar(reset){
        const rol = relacion.value;
        const titular = rol === 'titular';
        const elegido = Boolean(rol);
        if(titular && !eraTitular){ propios = {nombre:nombre.value, nacimiento:nacimiento.value}; nombre.value=datos.nombre; if(datos.nacimiento) nacimiento.value=datos.nacimiento; }
        else if(!titular && eraTitular){ nombre.value=propios.nombre; if(datos.nacimiento) nacimiento.value=propios.nacimiento; }
        nombre.readOnly=titular;
        nacimiento.readOnly=titular && Boolean(datos.nacimiento);
        document.getElementById('campo-nombre').hidden=titular;
        document.getElementById('campo-nacimiento').hidden=titular && Boolean(datos.nacimiento);
        document.getElementById('campo-dni-titular').hidden=!elegido || titular;
        dniTitular.disabled=!elegido || titular;
        document.getElementById('resumen-titular').hidden=!titular;
        document.getElementById('solicitante-heading').textContent=titular?'Confirma tus datos':'Datos '+(roles[rol] || 'del solicitante');
        document.getElementById('dni-label').textContent=titular?'Tu DNI':'DNI '+(roles[rol] || 'del solicitante');
        const familiar=rol==='madre'||rol==='padre';
        document.getElementById('dni-titular-label').textContent=familiar?'DNI de tu hijo o hija (opcional)':'DNI de la persona del acta (opcional)';
        document.getElementById('solicitante-ayuda').textContent=titular?'El nombre y la fecha disponibles ya están tomados del acta. Completa los datos restantes.':familiar?'En este bloque van tus datos, no los de tu hijo o hija.':'En este bloque van los datos de quien realiza el trámite, no los de la persona del acta.';
        document.getElementById('relacion-ayuda').textContent=titular?'El acta es tuya. No tendrás que repetir los datos que ya tenemos.':familiar?'Estás solicitando el acta de tu hijo o hija. Más abajo completarás tus propios datos.':'El titular es la persona cuyo nombre aparece en el acta.';
        document.getElementById('texto-declaracion').textContent=textos[rol] || 'Selecciona quién realiza la solicitud para leer la declaración.';
        if(reset) document.getElementById('declaracion').checked=false;
        eraTitular=titular;
    }
    relacion.addEventListener('change',()=>adaptar(true));
    adaptar(false);
})();
</script>
<script src="{{ asset('js/tramite-asistente.js') }}?v={{ filemtime(public_path('js/tramite-asistente.js')) }}"></script>
@endsection