@if(session('pago_confirmado') === true)
<dialog id="pago-confirmado" class="citizen-receipt" aria-labelledby="pago-confirmado-titulo">
<form method="dialog"><button class="receipt-close" aria-label="Cerrar confirmación">×</button></form>
<div class="receipt-icon"><i class="bi bi-check-lg" aria-hidden="true"></i></div>
<span class="citizen-eyebrow">CONFIRMACIÓN DEL PAGO</span>
<h2 id="pago-confirmado-titulo">¡Tu pago fue aprobado!</h2>
<p>Recibimos la confirmación de tu pago de prueba. Tu solicitud está lista para la revisión del Registro Civil.</p>
<div class="receipt-amount"><small>Importe confirmado</small><strong>S/ 10.30</strong><span>Modo de prueba · Sin movimiento de dinero real</span></div>
<p class="small">El pago no sustituye la revisión ni autoriza automáticamente la entrega del acta.</p>
<form method="dialog"><button class="btn btn-primary w-100">Entendido, continuar <i class="bi bi-arrow-right ms-2"></i></button></form>
</dialog>
<script>document.getElementById('pago-confirmado').showModal();</script>
@endif
