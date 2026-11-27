(() => {
    const main = document.querySelector('.municipal-system main.ui-clear');
    if (!main) return;
    // Associate older form labels without changing field names or submitted data.
    let count = 0;
    main.querySelectorAll('label:not([for])').forEach(label => {
        if (label.querySelector('input,select,textarea')) return;
        const sibling = label.nextElementSibling;
        const controls = sibling?.matches('input:not([type="hidden"]),select,textarea') ? [sibling]
            : sibling?.querySelectorAll('input:not([type="hidden"]),select,textarea');
        if (!controls || controls.length !== 1) return;
        const control = controls[0];
        if (!control.id) {
            let id;
            do { id = 'campo-municipal-' + (++count); } while (document.getElementById(id));
            control.id = id;
        }
        label.htmlFor = control.id;
    });
    main.querySelectorAll('table thead th:not([scope])').forEach(th => { th.scope = 'col'; });
    main.querySelectorAll('.table-responsive').forEach(wrapper => {
        const table = wrapper.querySelector('table');
        if (!table) return;
        wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', 'Tabla de resultados. Desplázate horizontalmente si es necesario.');
    });
    // Existing title text remains the source for compact action labels.
    main.querySelectorAll('a.btn[title],button.btn[title]').forEach(action => {
        if (!action.textContent.trim() && !action.hasAttribute('aria-label')) action.setAttribute('aria-label', action.title);
    });
    document.querySelectorAll('.sidebar-municipal a.nav-link.active').forEach(link => link.setAttribute('aria-current', 'page'));
})();
