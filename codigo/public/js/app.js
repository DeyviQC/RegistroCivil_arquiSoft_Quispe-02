document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Keep feedback visible until the person chooses to dismiss it.
});

// Preparar la impresión sin abrir otra pestaña.
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[href]');
    if (!link) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin || !/^\/actas\/\d+\/print\/?$/.test(url.pathname)) return;
    event.preventDefault();
    if (document.getElementById('impresionActaOculta')) return;
    const frame = document.createElement('iframe');
    frame.id = 'impresionActaOculta';
    frame.title = 'Preparación de impresión';
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1000px;height:1000px;border:0;';
    const aviso = document.createElement('div');
    aviso.className = 'alert alert-info position-fixed bottom-0 start-50 translate-middle-x';
    aviso.style.zIndex = '1100';
    aviso.setAttribute('role', 'status');
    aviso.textContent = 'Preparando impresión…';
    let timer;
    function limpiar() {
        clearTimeout(timer);
        window.removeEventListener('message', recibir);
        frame.remove(); aviso.remove();
    }
    function recibir(e) {
        if (e.origin !== window.location.origin || e.source !== frame.contentWindow) return;
        if (e.data === 'acta-impresion-lista') { clearTimeout(timer); aviso.remove(); }
        if (e.data === 'acta-impresion-finalizada') limpiar();
        if (e.data === 'acta-impresion-error') {
            limpiar(); window.alert('No se pudo preparar la impresión. Abra el PDF desde Ver PDF para intentarlo nuevamente.');
        }
    }
    window.addEventListener('message', recibir);
    timer = setTimeout(function () { limpiar(); window.alert('La preparación de impresión tardó demasiado. Inténtelo nuevamente.'); }, 120000);
    frame.src = url.href;
    document.body.appendChild(aviso); document.body.appendChild(frame);
});
