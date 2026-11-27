<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imprimir acta {{ $acta->numero_acta }}</title>
    <style>
        body { margin:0; font-family:Arial,sans-serif; background:#f6f5ef; color:#2f3329; }
        .controles { padding:24px; }
        button,a { padding:10px 16px; display:inline-block; }
        button { background:#3f4d2a; color:white; border:0; border-radius:6px; cursor:pointer; }
        .pagina { margin:16px auto; max-width:90%; background:white; }
        .pagina img { width:100%; display:block; }
        @media print {
            body { background:white; }
            .controles { display:none; }
            .pagina { margin:0; max-width:none; break-after:page; break-inside:avoid; }
            .pagina:last-child { break-after:auto; }
        }
    </style>
    <style id="tamanoPaginas"></style>
</head>
<body>
    <div class="controles">
        <p id="estado" role="status">Preparando el PDF para abrir la impresión…</p>
        <button id="reintentar" type="button" hidden>Imprimir</button>
        <a href="{{ route('actas.pdf', $acta) }}">Abrir PDF original</a>
    </div>
    <div id="paginas"></div>
    <script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
    <script>
    async function prepararImpresion() {
        const estado = document.getElementById('estado');
        try {
            pdfjsLib.GlobalWorkerOptions.workerSrc = '{{ asset('vendor/pdfjs/pdf.worker.min.js') }}';
            const pdf = await pdfjsLib.getDocument(@json(route('actas.pdf', $acta))).promise;
            for (let numero=1; numero<=pdf.numPages; numero++) {
                estado.textContent = 'Preparando página '+numero+' de '+pdf.numPages+'…';
                const pagina = await pdf.getPage(numero);
                const original = pagina.getViewport({scale:1});
                const viewport = pagina.getViewport({scale:2.5});
                const canvas = document.createElement('canvas');
                canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height);
                await pagina.render({canvasContext:canvas.getContext('2d'),viewport}).promise;
                const imagen = new Image(); imagen.alt = 'Página '+numero;
                imagen.src = canvas.toDataURL('image/png');
                await imagen.decode();
                const bloque = document.createElement('section'); bloque.className='pagina';
                bloque.style.page='pdfpagina'+numero;
                document.getElementById('tamanoPaginas').textContent += '@page pdfpagina'+numero+' {size:'+original.width+'pt '+original.height+'pt;margin:0;}';
                bloque.appendChild(imagen); document.getElementById('paginas').appendChild(bloque);
                canvas.width=0; canvas.height=0; pagina.cleanup();
            }
            document.getElementById('reintentar').hidden=false;
            estado.textContent='Documento listo. Si el navegador bloquea el diálogo automático, pulse Imprimir.';
            await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
            if (window.parent !== window) window.parent.postMessage('acta-impresion-lista', window.location.origin);
            window.focus(); window.print();
        } catch (error) {
            estado.textContent='No se pudo preparar la impresión. Puede abrir el PDF original desde el enlace.';
            if (window.parent !== window) window.parent.postMessage('acta-impresion-error', window.location.origin);
        }
    }
    document.getElementById('reintentar').addEventListener('click',()=>window.print());
    window.addEventListener('afterprint', function () {
        if (window.parent !== window) window.parent.postMessage('acta-impresion-finalizada', window.location.origin);
    });
    prepararImpresion();
    </script>
</body>
</html>
