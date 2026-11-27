<h1>Registro Civil municipal</h1>
<p>Tu solicitud <strong>N.º {{ $numero }}</strong> cambió a: <strong>{{ $etiqueta }}</strong>.</p>
@if($indicacion)<p>{{ $indicacion }}</p>@endif
@if($mensaje !== '')<p><strong>Mensaje de Registro Civil:</strong><br>{{ $mensaje }}</p>@endif
@if($enlace)
<p><a href="{{ $enlace }}" style="display:inline-block;padding:10px 18px;background:#2f6b4f;color:#fff;text-decoration:none;border-radius:6px">Ver mi solicitud</a></p>
<p style="font-size:13px;color:#555">Este enlace es personal y vale {{ \App\Mail\EstadoTramiteMail::DIAS_ENLACE }} días: no lo reenvíes. Después podrás entrar desde <a href="{{ route('portal.seguimiento') }}">Seguir mi solicitud</a> con tu DNI y correo.</p>
@else
<p><a href="{{ route('portal.seguimiento') }}">Consultar mi solicitud</a></p>
@endif
<p>Por seguridad, este correo no incluye datos del acta ni el documento.</p>
<p>Si no realizaste este trámite, puedes ignorar este mensaje.</p>
