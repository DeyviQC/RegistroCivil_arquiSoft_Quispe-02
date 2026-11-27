/* Official Firma Perú callbacks; only the server can confirm receipt of a signed PDF. */
(() => {
    const button = document.getElementById('firma-peru-iniciar');
    if (!button) return;
    const cancel = document.getElementById('firma-peru-cancelar');
    const status = document.getElementById('firma-peru-estado');
    let attempt = null, timer = null, pollBusy = false, initiating = false, generation = 0;
    const message = text => { status.textContent = text; };
    const stop = () => { clearInterval(timer); timer = null; button.disabled = false; cancel.hidden = true; };
    async function post(url, data) {
        const response = await fetch(url, {method: 'POST', credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': button.dataset.csrf},
            body: JSON.stringify(data)});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || 'No se pudo iniciar la firma. Recarga y vuelve a intentarlo.');
        return result;
    }
    async function poll() {
        if (!attempt || pollBusy) return;
        pollBusy = true;
        try {
            const response = await fetch(attempt.estado_url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) { stop(); message('La sesión terminó o no se pudo consultar el resultado. Recarga la solicitud.'); return; }
            const result = await response.json();
            if (result.recibida) { stop(); message('PDF firmado recibido y comprobado. Abriendo revisión…'); window.location.reload(); }
            else if (result.expirada) { stop(); message('El intento venció. Recarga para comenzar otra firma.'); }
        } catch (_) { message('No se pudo consultar el resultado. Comprueba tu conexión; no publiques hasta ver el PDF recibido.'); }
        finally { pollBusy = false; }
    }
    window.signatureInit = () => message('Firma Perú está iniciando. Elige tu certificado y la ubicación del sello en la ventana local.');
    window.signatureOk = () => { message('El firmador terminó. Comprobando que el servidor recibió el PDF…'); poll(); };
    window.signatureCancel = () => message('El firmador indicó cancelación. Puedes cancelar este intento o esperar si ya envió el documento.');
    button.addEventListener('click', async () => {
        if (initiating || attempt) return;
        if (!window.jqFirmaPeru || typeof window.startSignature !== 'function') { message('No cargó el componente oficial. Abre esta página en Microsoft Edge y comprueba tu conexión a internet.'); return; }
        initiating = true; button.disabled = true; const current = ++generation;
        message('Preparando el documento y autorizando la firma…');
        try {
            attempt = await post(button.dataset.url, {version: Number(button.dataset.version)});
            if (current !== generation) return;
            cancel.hidden = false;
            timer = setInterval(poll, 3000);
            window.startSignature(attempt.port, attempt.param);
            message('Abriendo Firma Perú. Autoriza la apertura si Edge lo solicita. Si no aparece, cancela el intento y revisa ClickOnce.');
        } catch (error) { stop(); message(error.message); }
        finally { initiating = false; }
    });
    cancel.addEventListener('click', async () => {
        if (!attempt) return;
        cancel.disabled = true;
        try {
            await post(button.dataset.cancelar, {intento: attempt.intento});
            ++generation; stop(); message('Intento cancelado. Recarga la página para comenzar de nuevo.'); button.disabled = true;
        } catch (_) { message('No se pudo confirmar la cancelación. Recarga y comprueba el estado de la solicitud.'); }
        finally { cancel.disabled = false; }
    });
    window.addEventListener('pagehide', () => clearInterval(timer));
})();
