@extends('layouts.app')
@section('titulo', 'Completar datos de padres')
@section('contenido')
<h1 class="h4">Madre y padre · Nacimiento N.º {{ $acta->numero_acta }}</h1><p>{{ $nacimiento->apellido_nombre }}</p><p>Completa únicamente los datos que puedes verificar en el documento. El PDF, la numeración y los datos originales se conservan.</p><a class="btn btn-outline-primary mb-3" href="{{ route('actas.pdf.visor', $acta) }}" target="_blank" rel="noopener">Consultar PDF original</a>
<form method="POST" action="{{ route('actas.filiacion.update', $acta) }}">@csrf @method('PATCH')<input type="hidden" name="filiacion_version" value="{{ $nacimiento->filiacion_version }}">@include('actas.campos-filiacion')<button class="btn btn-primary">Guardar datos de padres</button><a class="btn btn-outline-secondary" href="{{ route('actas.show', $acta) }}">Cancelar</a></form>
@endsection
