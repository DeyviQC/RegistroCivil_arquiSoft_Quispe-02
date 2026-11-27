(function () {
    const form = document.querySelector('[data-tramite-wizard]');
    if (!form) return;
    const panels = [...form.querySelectorAll('.applicant-section')];
    const steps = [...document.querySelectorAll('[data-wizard-step]')];
    const controls = document.getElementById('wizard-controls');
    const previous = document.getElementById('wizard-previous');
    const next = document.getElementById('wizard-next');
    const labels = ['El acta y tu relación', 'Tus datos personales', 'Contacto y confirmación'];
    let current = 0;
    let reached = 0;
    function valid() {
        for (const input of panels[current].querySelectorAll('input, select, textarea')) {
            if (!input.checkValidity()) {
                const details = input.closest('details');
                if (details) details.open = true;
                input.reportValidity();
                return false;
            }
        }
        return true;
    }
    function display(index, focus) {
        current = index;
        const summary = document.getElementById('contact-review-summary');
        const name = form.querySelector('[name="nombre"]');
        const relation = form.querySelector('[name="relacion"]');
        if (summary && name && relation) {
            document.getElementById('contact-review-name').textContent = name.value.trim();
            document.getElementById('contact-review-relation').textContent = relation.selectedOptions[0]?.textContent || '';
            summary.hidden = !name.value.trim();
        }
        reached = Math.max(reached, index);
        panels.forEach((panel, i) => panel.hidden = i !== index);
        steps.forEach((step, i) => {
            step.classList.toggle('is-active', i === index);
            step.classList.toggle('is-complete', i < index);
            step.disabled = i > reached;
            if (i === index) step.setAttribute('aria-current', 'step');
            else step.removeAttribute('aria-current');
            const number = step.querySelector('span');
            if (number) {
                number.textContent = i < index ? '✓' : String(i + 1);
                number.setAttribute('aria-hidden', 'true');
            }
            step.setAttribute('aria-label', 'Paso ' + (i + 1) + ': ' + labels[i] + (i < index ? ', completado' : i === index ? ', actual' : ', pendiente'));
        });
        previous.hidden = index === 0;
        next.hidden = index === panels.length - 1;
        controls.hidden = index === panels.length - 1;
        document.getElementById('wizard-caption').textContent = 'Paso ' + (index + 1) + ' de 3 · ' + labels[index];
        document.getElementById('wizard-progress').style.width = ((index + 1) / 3 * 100) + '%';
        if (focus) {
            const title = panels[index].querySelector('h2');
            title.tabIndex = -1;
            title.focus({preventScroll:true});
            panels[index].scrollIntoView({behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block:'start'});
        }
    }
    next.addEventListener('click', () => { if (valid()) display(current + 1, true); });
    previous.addEventListener('click', () => display(current - 1, true));
    document.getElementById('wizard-back-contact').addEventListener('click', () => display(1, true));
    steps.forEach((step, i) => step.addEventListener('click', () => { if (i <= current || valid()) display(i, true); }));
    // Native validation must focus the section containing any invalid field.
    form.addEventListener('invalid', event => {
        const index = panels.indexOf(event.target.closest('.applicant-section'));
        if (index !== -1 && index !== current) display(index, false);
    }, true);
    form.addEventListener('submit', event => {
        if (current < panels.length - 1) { event.preventDefault(); if (valid()) display(current + 1, true); }
    });
    form.classList.add('wizard-ready');
    document.querySelector('.wizard-navigation').hidden = false;
    document.getElementById('wizard-back-contact').hidden = false;
    const invalid = form.querySelector('[aria-invalid="true"]');
    display(invalid ? panels.indexOf(invalid.closest('.applicant-section')) : 0, false);
})();
