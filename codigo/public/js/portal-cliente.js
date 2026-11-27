(() => {
    const choices = document.getElementById('seleccion-tipo-acta');
    const questionnaire = document.getElementById('cuestionario-acta');
    const summary = document.getElementById('acta-seleccion-resumen');
    const changeType = document.getElementById('cambiar-tipo-acta');
    if (choices && questionnaire && summary && changeType) {
        const radios = [...choices.querySelectorAll('input[name="tipo"]')];
        const descriptions = {
            NACIMIENTO: ['Nacimiento', 'Nombres y apellidos completos del titular', 'Fecha de nacimiento'],
            MATRIMONIO: ['Matrimonio', 'Nombres y apellidos de ambos contrayentes', 'Fecha de matrimonio'],
            DEFUNCION: ['Defunción', 'Nombres y apellidos completos del titular', 'Fecha de fallecimiento']
        };
        const show = (focus) => {
            const selected = radios.find(r => r.checked);
            const data = selected && descriptions[selected.value];
            choices.hidden = !!data;
            questionnaire.hidden = !data;
            questionnaire.disabled = !data;
            summary.hidden = !data;
            if (!data) return;
            document.getElementById('acta-seleccion-nombre').textContent = 'Acta de ' + data[0].toLowerCase();
            // Preserve the required marker appended by the shared form script.
            [['titular', data[1]], ['fecha', data[2]]].forEach(([id, text]) => {
                const label = questionnaire.querySelector('label[for="' + id + '"]');
                if (label?.firstChild?.nodeType === Node.TEXT_NODE) label.firstChild.textContent = text + ' ';
            });
            const help = document.getElementById('titular-ayuda');
            help.textContent = selected.value === 'MATRIMONIO'
                ? 'Escribe los nombres completos de ambos contrayentes, tal como figuran en el acta.'
                : 'Escribe los nombres completos tal como figuran en el acta.';
            if (focus) { summary.scrollIntoView({block:'start'}); document.getElementById('titular').focus({preventScroll:true}); }
        };
        radios.forEach(radio => radio.addEventListener('change', () => show(true)));
        changeType.addEventListener('click', () => {
            choices.hidden = false; questionnaire.hidden = true; questionnaire.disabled = true; summary.hidden = true;
            const selected = radios.find(r => r.checked);
            // Clear only the choice; typed names and dates remain available when choosing again.
            radios.forEach(r => { r.checked = false; });
            choices.scrollIntoView({block:'start'});
            (selected || radios[0]).focus({preventScroll:true});
        });
        show(false);
    }
    const whatsapp = document.getElementById('entrega-whatsapp');
    const phone = document.getElementById('numero-entrega');
    const field = document.getElementById('campo-numero-entrega');
    if (whatsapp && phone && field) {
        const update = () => {
            field.hidden = !whatsapp.checked;
            phone.disabled = !whatsapp.checked;
            phone.required = whatsapp.checked;
        };
        whatsapp.addEventListener('change', update);
        update();
    }
    const submitted = new Map();
    document.querySelectorAll('form[method="POST"]').forEach(form => {
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (submitted.has(form)) { event.preventDefault(); return; }
            const button = event.submitter;
            if (!button || button.type !== 'submit') return;
            submitted.set(form, {button, html: button.innerHTML});
            button.disabled = true;
            button.textContent = 'Procesando…';
            form.setAttribute('aria-busy', 'true');
        });
    });
    window.addEventListener('pageshow', () => {
        submitted.forEach(({button, html}, form) => {
            button.disabled = false;
            button.innerHTML = html;
            form.removeAttribute('aria-busy');
        });
        submitted.clear();
    });
})();
