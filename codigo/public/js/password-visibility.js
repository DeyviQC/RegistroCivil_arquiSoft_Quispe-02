// Mostrar contraseñas únicamente mientras se mantiene presionado el ojo.
(function () {
    document.querySelectorAll('input[type="password"]').forEach(function (input, index) {
        if (input.dataset.ojoPreparado) return;
        input.dataset.ojoPreparado = 'true';
        if (!input.id) input.id = 'clave-campo-' + index;
        let contenedor = input.parentElement;
        if (!contenedor.classList.contains('password-field')) {
            contenedor = document.createElement('div');
            contenedor.className = 'password-field';
            input.before(contenedor); contenedor.appendChild(input);
        }
        const ojo = document.createElement('button');
        ojo.type = 'button'; ojo.className = 'password-eye';
        ojo.setAttribute('aria-label', 'Mantener presionado para mostrar la contraseña');
        ojo.setAttribute('aria-controls', input.id); ojo.setAttribute('aria-pressed', 'false');
        ojo.title = 'Mantener presionado para mostrar';
        ojo.innerHTML = '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
        contenedor.appendChild(ojo);
        function mostrar() { input.type = 'text'; ojo.setAttribute('aria-pressed', 'true'); }
        function ocultar() { input.type = 'password'; ojo.setAttribute('aria-pressed', 'false'); }
        ojo.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;
            e.preventDefault(); ojo.setPointerCapture(e.pointerId); mostrar();
        });
        ['pointerup', 'pointercancel', 'lostpointercapture', 'blur'].forEach(e => ojo.addEventListener(e, ocultar));
        document.addEventListener('pointerup', ocultar);
        ojo.addEventListener('keydown', function (e) { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); mostrar(); } });
        ojo.addEventListener('keyup', function (e) { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); ocultar(); } });
        ojo.addEventListener('click', e => e.preventDefault());
        window.addEventListener('blur', ocultar);
        document.addEventListener('visibilitychange', ocultar);
        if (input.form) input.form.addEventListener('submit', ocultar);
    });
})();