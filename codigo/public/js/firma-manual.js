/* Manual handoff: prepare an exact archive copy, download it, then accept a signed upload. */
(() => {
    const root = document.querySelector('[data-manual-sign]');
    if (!root) return;
    const form = root.querySelector('[data-prepare-original]');
    const download = root.querySelector('[data-manual-download]');
    const upload = root.querySelector('[data-upload-signed]');
    const status = root.querySelector('[data-manual-status]');
    let preparing = false;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (preparing) return;
        preparing = true;
        const button = form.querySelector('button');
        button.disabled = true;
        status.textContent = 'Preparando la copia para firmar…';
        try {
            const response = await fetch(form.action, {method:'POST', credentials:'same-origin', body:new FormData(form), headers:{'Accept':'application/json'}});
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || 'No se pudo preparar el documento. Recarga e inténtalo de nuevo.');
            const destination = new URL(result.descarga, window.location.href);
            if (destination.origin !== window.location.origin || !Number.isInteger(result.version)) throw new Error('Recarga la solicitud para continuar.');
            root.querySelectorAll('input[name="version"]').forEach(input => { input.value = result.version; });
            download.href = destination.href;
            download.hidden = false;
            form.hidden = true;
            upload.querySelectorAll('input,button').forEach(control => { control.disabled = false; });
            status.textContent = 'Descarga iniciada. Firma el archivo en Firma Perú y después súbelo en el paso 3.';
            download.click();
        } catch (error) { status.textContent = error.message || 'No se pudo descargar el documento. Comprueba tu conexión.'; }
        finally { preparing = false; button.disabled = false; }
    });
})();
