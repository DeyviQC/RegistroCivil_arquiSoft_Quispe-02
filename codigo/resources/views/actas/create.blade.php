@extends('layouts.app')

@section('titulo', 'Registrar acta')
@section('descripcion', 'Seleccione el tipo de acta, complete los datos y adjunte su PDF. Revise la información antes de guardar.')

@section('contenido')
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('actas.store') }}" enctype="multipart/form-data" id="formActa">
            @csrf

            <h6 class="text-muted mb-3">PASO 1 — Tipo de acta</h6>
            <p id="avisoTipo" class="alert alert-info">Seleccione nacimiento, matrimonio o defunción para mostrar los campos y registrar el acta.</p>
            <div class="row row-cols-3 g-3 mb-4">
                @foreach (['NACIMIENTO' => 'person-plus', 'MATRIMONIO' => 'heart', 'DEFUNCION' => 'flower1'] as $tipoOpcion => $icono)
                    <div class="col">
                        <input type="radio" class="btn-check" name="tipo" id="tipo{{ $tipoOpcion }}"
                               value="{{ $tipoOpcion }}" autocomplete="off"
                               {{ old('tipo') === $tipoOpcion ? 'checked' : '' }} required>
                        <label class="btn btn-outline-primary w-100 py-3" for="tipo{{ $tipoOpcion }}">
                            <i class="bi bi-{{ $icono }} d-block fs-3 mb-1"></i>
                            {{ $tipoOpcion === 'DEFUNCION' ? 'Defunción' : ucfirst(strtolower($tipoOpcion)) }}
                        </label>
                    </div>
                @endforeach
            </div>

            <fieldset id="datosActa" class="d-none" disabled>
            <legend class="fs-6 fw-semibold" id="tituloTipo"></legend>
            <h6 class="text-muted mb-3">PASO 2 — Datos generales del acta</h6>
            <div class="row g-3 mb-2">
                <div class="col-md-6">
                    <label class="form-label">N° de acta</label>
                    <input type="text" name="numero_acta" class="form-control" value="{{ old('numero_acta') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fecha de registro / inscripción</label>
                    <input type="date" name="fecha_acta" class="form-control" value="{{ old('fecha_acta') }}" required max="{{ now('America/Lima')->format('Y-m-d') }}">
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Sobre / Archivo físico</label>
                    <input type="text" id="sobreAutomatico" class="form-control" readonly placeholder="Seleccione el tipo de acta">
                </div>
                <div class="col-md-4">
                    <label class="form-label">N° correlativo (dentro del sobre, máx. 25)</label>
                    <input type="text" id="correlativoAutomatico" class="form-control" readonly placeholder="Seleccione el tipo de acta">
                </div>
                <div class="col-md-4">
                    <label class="form-label">N° de orden automático</label>
                    <input type="text" id="ordenAutomatico" class="form-control" readonly placeholder="Seleccione el tipo de acta">
                </div>
                <div class="col-12"><small class="text-muted">Se asignan automáticamente al guardar. Cada sobre admite 25 actas de su tipo.</small></div>
            </div>

            <h6 class="text-muted mb-3">PASO 3 — Datos del registro simple</h6><p class="small text-muted">Todos los datos del registro son obligatorios. En matrimonio, ingrese los DNI de ambos contrayentes. Las observaciones son opcionales. Si el acta no indica DNI/CUI, marque la casilla correspondiente.</p>

            {{-- ===================== NACIMIENTO (simple) ===================== --}}
            <div class="seccion-tipo" data-tipo="NACIMIENTO">
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">Apellidos y nombres</label><input type="text" name="apellido_nombre" class="form-control" value="{{ old('apellido_nombre') }}" required></div>
                    <div class="col-md-3"><label class="form-label">DNI</label><input type="text" name="dni" class="form-control" value="{{ old('dni') }}" required><div class="form-check mt-2"><input class="form-check-input sin-dni" type="checkbox" name="dni_no_consignado" value="1" {{ old('dni_no_consignado') ? 'checked' : '' }}><label class="form-check-label small">El acta no consigna DNI/CUI</label></div></div>
                    <div class="col-md-3"><label class="form-label">Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" class="form-control" value="{{ old('fecha_nacimiento') }}" required max="{{ now('America/Lima')->format('Y-m-d') }}"></div>
                </div>
            </div>

            <div class="seccion-tipo-adicional" data-tipo="NACIMIENTO">@include('actas.campos-filiacion')</div>

            {{-- ===================== MATRIMONIO (simple) ===================== --}}
            <div class="seccion-tipo d-none" data-tipo="MATRIMONIO">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Apellidos y nombres de ambos contrayentes</label>
                        <input type="text" name="apellido_nombre" class="form-control" placeholder="Ej: Pérez López Juan / García Ruiz María" value="{{ old('apellido_nombre') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">DNI (ambos)</label>
                        <input type="text" name="dni" class="form-control" placeholder="12345678 / 87654321" value="{{ old('dni') }}" required>
                    </div>
                    <div class="col-md-3"><label class="form-label">Fecha de celebración</label><input type="date" name="fecha_matrimonio" class="form-control" value="{{ old('fecha_matrimonio') }}" required max="{{ now('America/Lima')->format('Y-m-d') }}"></div>
                </div>
            </div>

            {{-- ===================== DEFUNCION (simple) ===================== --}}
            <div class="seccion-tipo d-none" data-tipo="DEFUNCION">
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">Apellidos y nombres</label><input type="text" name="apellido_nombre" class="form-control" value="{{ old('apellido_nombre') }}" required></div>
                    <div class="col-md-3"><label class="form-label">DNI</label><input type="text" name="dni" class="form-control" value="{{ old('dni') }}" required><div class="form-check mt-2"><input class="form-check-input sin-dni" type="checkbox" name="dni_no_consignado" value="1" {{ old('dni_no_consignado') ? 'checked' : '' }}><label class="form-check-label small">El acta no consigna DNI/CUI</label></div></div>
                    <div class="col-md-3"><label class="form-label">Fecha de fallecimiento</label><input type="date" name="fecha_defuncion" class="form-control" value="{{ old('fecha_defuncion') }}" required max="{{ now('America/Lima')->format('Y-m-d') }}"></div>
                    <div class="col-md-3"><label class="form-label">Edad</label><input type="text" name="edad" maxlength="20" class="form-control" placeholder="Ej. 83 años o 1 día" value="{{ old('edad') }}" required></div><div class="col-md-12"><label class="form-label">Causa</label><input type="text" name="causa_defuncion" class="form-control" value="{{ old('causa_defuncion') }}" required></div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Observaciones (opcional)</label>
                <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones') }}</textarea>
            </div>

            <h6 class="text-muted mb-3">PASO 4 — Documento PDF</h6>



            <div class="mb-4">
                <input type="file" name="pdf" id="inputPdf" accept="application/pdf" class="form-control" required>
                <small class="text-muted" id="ayudaPdf">Adjunte el PDF descargado del acta.</small>
            </div>

            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-save me-1"></i> Guardar registro
            </button>
            <a href="{{ route('actas.index') }}" class="btn btn-outline-secondary px-4">Cancelar</a>
            </fieldset>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
var siguientes = @json($siguientes);
function actualizarTipoVisible(tipoSeleccionado) {
    var seleccionado = Boolean(siguientes[tipoSeleccionado]);
    document.getElementById('datosActa').classList.toggle('d-none', !seleccionado);
    document.getElementById('datosActa').disabled = !seleccionado;
    document.getElementById('avisoTipo').classList.toggle('d-none', seleccionado);
    document.getElementById('tituloTipo').textContent = seleccionado ? 'Registrar acta de ' + tipoSeleccionado.toLowerCase() : '';
    var siguiente = siguientes[tipoSeleccionado];
    document.getElementById('sobreAutomatico').value = siguiente ? siguiente.sobre : '';
    document.getElementById('correlativoAutomatico').value = siguiente ? siguiente.correlativo : '';
    document.getElementById('ordenAutomatico').value = siguiente ? siguiente.orden : '';
    document.querySelectorAll('.seccion-tipo, .seccion-tipo-adicional').forEach(function (seccion) {
        var esActiva = seccion.dataset.tipo === tipoSeleccionado;
        seccion.classList.toggle('d-none', !esActiva);
        seccion.querySelectorAll('input, select, textarea').forEach(function (campo) {
            campo.disabled = !esActiva;
        });
    });
    document.querySelectorAll('.seccion-tipo').forEach(actualizarDni);
}

document.querySelectorAll('input[name="tipo"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        actualizarTipoVisible(radio.value);
    });
    document.querySelectorAll('.seccion-tipo').forEach(actualizarDni);
});

function actualizarDni(seccion) {
    var casilla=seccion.querySelector('.sin-dni');
    if(!casilla)return;
    var dni=seccion.querySelector('input[name="dni"]');
    var activa=!seccion.classList.contains('d-none');
    if(casilla.checked){if(dni.value)dni.dataset.valorAnterior=dni.value;dni.value='';}
    else if(!dni.value && dni.dataset.valorAnterior){dni.value=dni.dataset.valorAnterior;}
    dni.disabled=!activa||casilla.checked;dni.required=activa&&!casilla.checked;
}
document.querySelectorAll('.sin-dni').forEach(function(casilla){
    casilla.id='sin-dni-'+casilla.closest('.seccion-tipo').dataset.tipo;
    casilla.nextElementSibling.htmlFor=casilla.id;
    casilla.addEventListener('change',function(){actualizarDni(casilla.closest('.seccion-tipo'));});
});
var tipoInicial = document.querySelector('input[name="tipo"]:checked');
actualizarTipoVisible(tipoInicial ? tipoInicial.value : '');

</script>
@endpush
