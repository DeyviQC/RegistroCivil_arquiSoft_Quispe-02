document.addEventListener('DOMContentLoaded', function () {
    // Confirmación genérica para acciones sensibles (activar/desactivar usuario).
    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Auto-cierre de alertas tipo toast/alert después de unos segundos.
    document.querySelectorAll('.alert.fade.show').forEach(function (alerta) {
        setTimeout(function () {
            var instancia = bootstrap.Alert.getOrCreateInstance(alerta);
            instancia.close();
        }, 6000);
    });
});
