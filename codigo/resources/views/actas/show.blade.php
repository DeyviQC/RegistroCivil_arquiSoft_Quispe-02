@extends('layouts.app')

@section('titulo', 'ACTA DE ' . $acta->tipo)

@section('contenido')
@foreach($incidencias as $incidencia)
<div class="card mb-3"><div class="card-body d-flex align-items-center gap-3 flex-wrap">@include('incidencias.badge')<span class="flex-grow-1">{{ $incidencia['resumen'] }}</span><a class="btn btn-outline-secondary btn-sm" href="{{ route('incidencias.index', ['detalle' => $incidencia['id']]) }}#detalle-incidencia">Ver incidencia</a></div></div>
@endforeach
<div class="certificado mx-auto" style="max-width: 900px;">
    <div class="certificado-encabezado">
        <div class="subtitulo">Municipalidad Distrital Andrés Avelino Cáceres Dorregaray</div>
        <h3 class="mt-2 mb-0">ACTA DE {{ $acta->tipo }}</h3>
        <div class="text-muted mt-1">N° de acta: {{ $acta->numero_acta }} &nbsp;·&nbsp; Año {{ $acta->anio }}</div>
        <div class="text-muted mt-1">N° de orden ({{ strtolower($acta->tipo) }}): <strong>{{ $acta->orden_tipo }}</strong></div>
        <div class="text-muted small mt-1">
            <i class="bi bi-archive me-1"></i> Sobre: <strong>{{ $acta->sobre }}</strong>
            &nbsp;·&nbsp; Correlativo: <strong>{{ $acta->correlativo }}</strong>
        </div>
        <div class="mt-3">
            @if ($acta->esBorrador())
                <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill fw-bold">
                    <i class="bi bi-hourglass-split me-1"></i> PENDIENTE DE PDF
                </span>
            @else
                <span class="badge-bloqueada">
                    <i class="bi bi-shield-lock-fill me-1"></i> ACTA BLOQUEADA / SOLO LECTURA
                </span>
            @endif
        </div>
    </div>

    <div class="p-4 p-md-5">
        @if ($acta->tipo === 'NACIMIENTO' && $acta->nacimiento)
            @php($d = $acta->nacimiento)
            <section class="border rounded p-3 mb-3"><div class="d-flex flex-wrap justify-content-between gap-2"><h6>Datos de madre y padre</h6>@can('create', App\Models\Acta::class)<a href="{{ route('actas.filiacion.edit', $acta) }}" class="btn btn-sm btn-outline-primary">Completar / revisar datos de padres</a>@endcan</div><div class="row g-3 mt-1">@foreach(['madre_nombre' => 'Nombres de la madre', 'madre_dni' => 'DNI de la madre', 'padre_nombre' => 'Nombres del padre', 'padre_dni' => 'DNI del padre'] as $campo => $etiqueta)<div class="col-md-6"><div class="dato-label">{{ $etiqueta }}</div><div class="dato-valor">{{ $d->{$campo.'_estado'} === 'REGISTRADO' ? $d->$campo : (App\Models\ActaNacimiento::ESTADOS_FILIACION[$d->{$campo.'_estado'}] ?? 'Pendiente de revisar') }}</div></div>@endforeach</div></section>
            <div class="row g-4">
                <div class="col-md-8"><div class="dato-label">Apellidos y nombres</div><div class="dato-valor">{{ $d->apellido_nombre }}</div></div>
                <div class="col-md-4"><div class="dato-label">DNI / CUI</div><div class="dato-valor">{{ $d->dni_no_consignado ? 'No consignado en el acta' : ($d->dni ?: 'No registrado') }}</div></div>
                <div class="col-md-4"><div class="dato-label">Fecha de nacimiento</div><div class="dato-valor">{{ $d->fecha_nacimiento->format('d/m/Y') }}</div></div>
                <div class="col-md-4"><div class="dato-label">Fecha de inscripción</div><div class="dato-valor">{{ $acta->fecha_acta->format('d/m/Y') }}</div></div>
            </div>
        @elseif ($acta->tipo === 'MATRIMONIO' && $acta->matrimonio)
            @php($d = $acta->matrimonio)
            <div class="row g-4">
                <div class="col-md-8"><div class="dato-label">Apellidos y nombres de ambos contrayentes</div><div class="dato-valor">{{ $d->apellido_nombre }}</div></div>
                <div class="col-md-4"><div class="dato-label">DNI de ambos (en el mismo orden)</div><div class="dato-valor">{{ $d->dni ?? '—' }}</div></div>
                <div class="col-md-4"><div class="dato-label">Fecha de celebración</div><div class="dato-valor">{{ $d->fecha_matrimonio->format('d/m/Y') }}</div></div>
                <div class="col-md-4"><div class="dato-label">Fecha de inscripción</div><div class="dato-valor">{{ $acta->fecha_acta->format('d/m/Y') }}</div></div>
            </div>
        @elseif ($acta->tipo === 'DEFUNCION' && $acta->defuncion)
            @php($d = $acta->defuncion)
            <div class="row g-4">
                <div class="col-md-8"><div class="dato-label">Nombre completo</div><div class="dato-valor">{{ $d->nombre_completo }}</div></div>
                <div class="col-md-4"><div class="dato-label">DNI del difunto</div><div class="dato-valor">{{ $d->dni_no_consignado ? 'No consignado en el acta' : ($d->dni ?: 'No registrado') }}</div></div>
                <div class="col-md-4"><div class="dato-label">Edad según índice</div><div class="dato-valor">{{ $d->edad ? (is_numeric($d->edad) ? $d->edad . ' años' : $d->edad) : 'No registrada' }}</div></div>
                <div class="col-md-4"><div class="dato-label">Fecha de fallecimiento</div><div class="dato-valor">{{ $d->fecha_defuncion->format('d/m/Y') }}</div></div>
                <div class="col-md-4"><div class="dato-label">Fecha de inscripción</div><div class="dato-valor">{{ $acta->fecha_acta->format('d/m/Y') }}</div></div>
                <div class="col-12"><div class="dato-label">Causa de defunción</div><div class="dato-valor">{{ $d->causa_defuncion }}</div></div>
            </div>
        @endif

        @if ($acta->observaciones)
            <div class="mt-4"><div class="dato-label">Observaciones</div><div class="dato-valor" style="white-space: pre-line;">{{ $acta->observaciones }}</div></div>
        @endif

        <hr class="my-4" style="border-color: var(--dorado-claro);">

        @if ($acta->esBorrador())
            <div class="alert alert-warning">
                <i class="bi bi-info-circle me-1"></i>
                Este registro anterior está pendiente de PDF. Adjunte el documento descargado para completar el registro.
            </div>


            <form method="POST" action="{{ route('actas.completar-pdf', $acta) }}" enctype="multipart/form-data" class="mt-3 d-flex gap-2 align-items-start flex-wrap">
                @csrf
                <input type="file" name="pdf" accept="application/pdf" class="form-control" style="max-width: 320px;" required>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-upload me-1"></i> Adjuntar PDF y completar registro
                </button>
            </form>
        @else
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">
                    Registrado por <strong>{{ $acta->usuario->name }}</strong> el {{ $acta->created_at->format('d/m/Y H:i') }}
                </span>
                <div>
                    <a href="{{ route('actas.pdf.visor', $acta) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Ver PDF adjunto
                    </a>
                    @if($acta->firmado_ruta)
                    <a href="{{ route('actas.pdf-firmado', $acta) }}" target="_blank" rel="noopener" class="btn {{ $acta->copiaFirmadaVigente() ? 'btn-success' : 'btn-outline-secondary' }}">
                        <i class="bi bi-patch-check me-1"></i> Copia firmada
                    </a>
                    @endif

                    <a href="{{ route('actas.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
