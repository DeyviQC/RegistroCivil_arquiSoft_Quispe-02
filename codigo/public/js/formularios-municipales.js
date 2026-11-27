(() => {
    // Use the actual required attribute, including conditional business fields.
    const controls = 'input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]),select,textarea';
    let sequence = 0;
    const labels = new Map();
    document.querySelectorAll('form').forEach(form => {
        form.querySelectorAll(controls).forEach(field => {
            let label = field.id ? [...form.querySelectorAll('label[for]')].find(l => l.htmlFor === field.id) : null;
            if (!label && field.previousElementSibling?.matches('label')) label = field.previousElementSibling;
            if (!label) return;
            if (!field.id) { do { field.id = 'formulario-campo-' + (++sequence); } while (document.querySelectorAll('[id="' + field.id + '"]').length > 1); }
            label.htmlFor = field.id;
            labels.set(field, label);
            const update = () => {
                const required = field.required && !field.disabled && !field.readOnly;
                field.setAttribute('aria-required', String(required));
                let badge = label.querySelector('.field-required');
                if (required && !badge) {
                    badge = document.createElement('span'); badge.className = 'field-required';
                    badge.textContent = 'Obligatorio'; label.append(badge);
                } else if (!required && badge) badge.remove();
            };
            update();
            new MutationObserver(update).observe(field, {attributes:true,attributeFilter:['required','disabled','readonly']});
            field.addEventListener('invalid', () => field.setAttribute('aria-invalid','true'));
            field.addEventListener('input', () => { if (field.validity.valid) field.removeAttribute('aria-invalid'); });
            field.addEventListener('change', () => { if (field.validity.valid) field.removeAttribute('aria-invalid'); });
        });
        const password = form.querySelector('input[name="password"]');
        const confirmation = form.querySelector('input[name="password_confirmation"]');
        const current = form.querySelector('input[name="current_password"]');
        if (password && confirmation) {
            const update = () => {
                confirmation.required = password.required || password.value.length > 0;
                if (current) current.required = password.value.length > 0;
            };
            password.addEventListener('input', update); update();
        }
    });
    // POST feedback is already handled by portal-cliente on public pages.
    const busy = new Map();
    if (document.body.classList.contains('municipal-system')) {
        document.querySelectorAll('form').forEach(form => {
            if ((form.method || '').toLowerCase() !== 'post') return;
            form.addEventListener('submit', event => {
                if (event.defaultPrevented) return;
                if (busy.has(form)) { event.preventDefault(); return; }
                const button = event.submitter;
                if (!button || button.name) return; // Preserve named submitter values.
                busy.set(form, {button, text:button.innerHTML});
                button.disabled = true;
                button.textContent = 'Guardando…';
                form.setAttribute('aria-busy','true');
            });
        });
    }
    window.addEventListener('pageshow', () => {
        busy.forEach(({button,text},form) => {button.disabled=false;button.innerHTML=text;form.removeAttribute('aria-busy');});
        busy.clear();
    });
})();
