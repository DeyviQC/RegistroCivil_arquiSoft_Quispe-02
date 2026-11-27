@extends('portal.layout')
@section('titulo', 'Verifica tu correo')
@section('contenido')
<div class="citizen-code-layout"><section class="citizen-code-art"><i class="bi bi-envelope-check" aria-hidden="true"></i><span class="citizen-eyebrow">VERIFICACIÓN DE ACCESO</span><h1>Verifica tu correo</h1><p>Ingresa el código que enviamos a tu correo para continuar.</p></section>
<section class="card"><div class="card-body"><span class="citizen-eyebrow">VERIFICACIÓN POR CORREO</span><h2 class="h4 mt-3">Revisa tu bandeja de entrada</h2><p class="text-muted small">Busca el correo de Registro Civil. Si no lo encuentras, revisa Spam o correo no deseado.</p>
@if(config('mail.default') === 'log')<div class="alert alert-warning">El envío está en modo de pruebas: el código se guarda localmente y no llegará a tu correo.</div>@endif
<form method="POST" action="{{ route('portal.verificar') }}">@csrf<label for="codigo" class="form-label">Código de 6 dígitos</label><input id="codigo" name="codigo" class="form-control otp-input mb-3" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required><p class="small text-muted"><i class="bi bi-clock me-1"></i>Válido por 10 minutos · Máximo 5 intentos</p><button class="btn btn-primary w-100">Verificar y continuar <i class="bi bi-arrow-right ms-2"></i></button></form>
<form method="POST" action="{{ route('portal.reenviar') }}" class="mt-4 text-center">@csrf<p class="small text-muted mb-2">¿No recibiste el correo? Puedes pedir otro código después de un minuto.</p><button class="btn btn-outline-secondary">Reenviar código</button></form></div></section></div>
@endsection
