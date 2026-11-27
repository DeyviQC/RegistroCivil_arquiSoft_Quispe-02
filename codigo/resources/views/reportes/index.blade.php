@extends('layouts.app')
@section('titulo', 'Reportes')
@section('descripcion', 'Seleccione un reporte, aplique los filtros y consulte los resultados. Puede descargarlos en Excel o PDF.')
@section('contenido')
<div class="card mb-3"><div class="card-body"><h2 class="h5">Preparar reporte</h2><p id="descripcionReporte" class="text-muted">{{ $configuracion[$reporte]['descripcion'] }}</p>
<form method="GET" class="row g-3">
<div class="col-md-4"><label class="form-label">Reporte</label><select name="reporte" id="tipoReporte" class="form-select">
@foreach($configuracion as $valor=>$config) @if($valor!=='actividad' || auth()->user()->can('viewAny',\App\Models\Auditoria::class))<option value="{{ $valor }}" @selected($reporte===$valor)>{{ $config['titulo'] }}</option>@endif @endforeach
</select></div>
@foreach(['fecha_desde'=>'Desde','fecha_hasta'=>'Hasta'] as $campo=>$texto)<div class="col-md-4 campo-reporte {{ !in_array($campo,$configuracion[$reporte]['filtros']) ? 'd-none' : '' }}" data-campo="{{ $campo }}"><label class="form-label">{{ $texto }}</label><input type="date" name="{{ $campo }}" max="{{ now('America/Lima')->format('Y-m-d') }}" value="{{ $filtros[$campo] ?? '' }}" class="form-control"></div>@endforeach
<div class="col-md-3 campo-reporte" data-campo="tipo"><label class="form-label">Tipo de acta</label><select name="tipo" class="form-select"><option value="">Todos</option>@foreach(['NACIMIENTO','DEFUNCION','MATRIMONIO'] as $tipo)<option @selected(($filtros['tipo'] ?? '')===$tipo)>{{ $tipo }}</option>@endforeach</select></div>
<div class="col-md-3 campo-reporte {{ $reporte!=='resumen' ? 'd-none' : '' }}" data-campo="mes"><label class="form-label">Mes de inscripción</label><select name="mes" class="form-select" @disabled($reporte!=='resumen')><option value="">Seleccione el mes</option>@foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i=>$nombre)<option value="{{ $i+1 }}" @selected(($filtros['mes']??'')==($i+1))>{{ $nombre }}</option>@endforeach</select></div>
@foreach(['anio'=>'Año de inscripción'] as $campo=>$texto)<div class="col-md-3 campo-reporte {{ !in_array($campo,$configuracion[$reporte]['filtros']) ? 'd-none' : '' }}" data-campo="{{ $campo }}"><label class="form-label">{{ $texto }}</label><input type="number" name="{{ $campo }}" min="{{ $campo==='anio'?1900:1 }}" max="{{ $campo==='anio'?now('America/Lima')->year:($campo==='correlativo'?25:999999) }}" value="{{ $filtros[$campo] ?? '' }}" class="form-control"></div>@endforeach
<div class="col-md-4 campo-reporte" data-campo="usuario_id"><label class="form-label">Usuario responsable</label><select name="usuario_id" class="form-select"><option value="">Todos</option>@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected(($filtros['usuario_id'] ?? '')==$usuario->id)>{{ $usuario->name }}</option>@endforeach</select></div>
<div class="col-12 d-flex gap-2"><button class="btn btn-primary">Consultar</button><a href="{{ route('reportes.index') }}" class="btn btn-outline-secondary">Limpiar filtros</a></div></form>
<p id="ayudaReporte" class="small text-muted mt-3"></p>
</div></div>
<div id="avisoFiltros" class="alert alert-warning d-none" role="status">Cambió los filtros. Pulse Consultar para actualizar los resultados y las descargas.</div><div class="card"><div class="card-body"><div class="d-flex justify-content-between flex-wrap gap-2 mb-3"><div><h5>{{ $titulo }} @if(in_array($reporte,['resumen','anual'])) · {{ $filtros['anio'] }} @if($reporte==='resumen') · Mes {{ $filtros['mes'] }} @endif @endif</h5>{{ $total }} {{ $reporte==='sobres'?'sobres':(in_array($reporte,['resumen','anual'])?'filas de resumen':'registros') }}</div><div>
<a class="btn btn-outline-success" href="{{ route('reportes.excel', array_diff_key($filtros,['page'=>true])) }}"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i> Descargar Excel</a>
<a class="btn btn-outline-danger" href="{{ route('reportes.pdf', array_diff_key($filtros,['page'=>true])) }}"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i> Descargar PDF</a></div></div>
<p class="text-muted small">Las descargas incluyen todos los resultados de los filtros aplicados.</p>
@if(in_array($reporte,['resumen','anual']))<div class="row g-3 mb-3">@foreach(['total'=>'Total de actas','con'=>'Con PDF disponible','sin'=>'Pendientes de PDF'] as $clave=>$etiqueta)<div class="col-md-4"><div class="border rounded p-3"><div>{{ $etiqueta }}</div><strong class="fs-3">{{ $metricas[$clave] }}</strong></div></div>@endforeach</div>@endif
<div class="table-responsive"><table class="table table-striped"><thead><tr>
@foreach($cabeceras as $c)<th>{{ $c }}</th>@endforeach
@if($reporte==='sobres')<th>Actas del sobre</th>@endif
</tr></thead><tbody>
@forelse($registros as $fila)<tr>@foreach($fila as $v)<td>{{ $v }}</td>@endforeach
@if($reporte==='sobres')<td>@if($fila[1]!=='Sin asignar')<a class="btn btn-outline-primary btn-sm text-nowrap" href="{{ route('actas.index',['tipo'=>$fila[0],'sobre'=>$fila[1]]) }}"><i class="bi bi-folder2-open me-1" aria-hidden="true"></i> Ver actas</a>@endif</td>@endif
</tr>@empty<tr><td colspan="{{ count($cabeceras)+($reporte==='sobres'?1:0) }}">No hay resultados con estos filtros.</td></tr>@endforelse
</tbody></table></div>{{ $registros->links() }}</div></div>
@endsection
@push('scripts')
<script>
const configReportes=@json($configuracion);
const selector=document.getElementById('tipoReporte');
const form=selector.form;
const actual={anio:{{ now('America/Lima')->year }},mes:{{ now('America/Lima')->month }}};
function campo(n){return form.elements.namedItem(n);}

function actualizarReporte(limpiar){
 const config=configReportes[selector.value];
 document.getElementById('descripcionReporte').textContent=config.descripcion;
 document.querySelectorAll('.campo-reporte').forEach(function(contenedor){
  const c=contenedor.querySelector('input,select');
  const permitido=config.filtros.includes(contenedor.dataset.campo);
  contenedor.classList.toggle('d-none',!permitido);c.disabled=!permitido;c.required=false;
  if(!permitido||limpiar)c.value='';
 });
 if(['resumen','anual'].includes(selector.value)){campo('anio').required=true;if(!campo('anio').value)campo('anio').value=actual.anio;}
 if(selector.value==='resumen'){campo('mes').required=true;if(!campo('mes').value)campo('mes').value=actual.mes;}

 const ayuda=document.getElementById('ayudaReporte');
 ayuda.textContent=selector.value==='sobres'?'Inventario completo de sobres. No se filtra por fechas ni usuario para que la ocupación sea real.':['resumen','anual'].includes(selector.value)?'Los totales usan la fecha de inscripción. Seleccione el periodo y pulse Consultar.':(['actas','pendientes'].includes(selector.value)?'Las fechas corresponden a la inscripción. Filtre el periodo, tipo de acta y usuario para generar el reporte.':'Las fechas corresponden a la acción registrada, en hora de Lima.');
}
selector.addEventListener('change',()=>actualizarReporte(true));
form.addEventListener('change',()=>document.getElementById('avisoFiltros').classList.remove('d-none'));
actualizarReporte(false);
</script>
@endpush
