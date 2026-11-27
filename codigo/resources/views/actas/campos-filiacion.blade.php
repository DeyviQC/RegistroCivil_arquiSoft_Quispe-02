<section class="border rounded p-3 mb-3"><h6>Datos de madre y padre</h6><p class="small text-muted">Transcribe desde el acta original. Si el nombre o DNI no figura, selecciona «No consignado». Si no se puede leer, selecciona «No legible». Cada campo se revisa por separado.</p>
@foreach(['madre' => 'Madre', 'padre' => 'Padre'] as $persona => $etiqueta)
<fieldset class="mb-3"><legend class="h6">{{ $etiqueta }}</legend><div class="row g-3">
@foreach(['nombre' => 'Nombres y apellidos', 'dni' => 'DNI'] as $tipoCampo => $nombreCampo)
@php($campo = $persona.'_'.$tipoCampo)
@php($estadoCampo = old($campo.'_estado', isset($nacimiento) ? ($nacimiento->{$campo.'_estado'} ?? '') : 'REGISTRADO'))
<div class="col-md-6 grupo-filiacion"><label class="form-label" for="{{ $campo }}">{{ $nombreCampo }} de {{ strtolower($etiqueta) }}</label><input id="{{ $campo }}" name="{{ $campo }}" class="form-control valor-filiacion" value="{{ old($campo, isset($nacimiento) ? $nacimiento->$campo : '') }}" maxlength="{{ $tipoCampo === 'dni' ? 8 : 200 }}" @if($tipoCampo === 'dni') inputmode="numeric" pattern="[0-9]{8}" @endif><label class="small mt-2" for="{{ $campo }}_estado">Estado del dato</label><select id="{{ $campo }}_estado" name="{{ $campo }}_estado" class="form-select estado-filiacion" required><option value="">Selecciona después de revisar</option>@foreach(App\Models\ActaNacimiento::ESTADOS_FILIACION as $valor => $texto)@if($valor !== 'PENDIENTE')<option value="{{ $valor }}" @selected($estadoCampo === $valor)>{{ $texto }}</option>@endif @endforeach</select></div>
@endforeach</div></fieldset>@endforeach</section>
@push('scripts')<script>
document.querySelectorAll('.estado-filiacion').forEach(function(select){function actualizar(){var input=select.closest('.grupo-filiacion').querySelector('.valor-filiacion');input.readOnly=select.value!=='REGISTRADO';input.required=select.value==='REGISTRADO'&&!input.disabled;if(input.readOnly)input.value='';}select.addEventListener('change',actualizar);actualizar();});
</script>@endpush
