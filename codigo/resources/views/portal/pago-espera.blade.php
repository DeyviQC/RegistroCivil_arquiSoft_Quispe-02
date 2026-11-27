@extends('portal.layout')
@section('contenido')
<h1 class="h3">Espera un momento para abrir el pago</h1>
<div class="alert alert-warning">Se intentó abrir el formulario varias veces. Puedes volver a intentarlo en {{ $segundos }} segundos.</div>
<p>Tu solicitud sigue registrada. Este aviso no confirma ningún cobro. Si ya pagaste, revisa primero el estado de tu solicitud.</p>
<a href="{{ route('portal.estado') }}" class="btn btn-primary">Volver a mi solicitud</a>
@endsection
