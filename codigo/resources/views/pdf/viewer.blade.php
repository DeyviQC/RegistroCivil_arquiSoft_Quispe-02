@extends('layouts.app')

@section('titulo', 'Visor PDF - Acta ' . $acta->numero_acta)

@section('contenido')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <strong>ACTA DE {{ $acta->tipo }}</strong>
            <span class="text-muted">N° {{ $acta->numero_acta }}/{{ $acta->anio }}</span>
        </div>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" id="btnPaginaAnterior"><i class="bi bi-chevron-left"></i> Anterior</button>
            <span class="btn btn-outline-secondary disabled" id="indicadorPagina" aria-live="polite">Página 1 / 1</span>
            <button class="btn btn-outline-secondary" id="btnPaginaSiguiente">Siguiente <i class="bi bi-chevron-right"></i></button>
            <button class="btn btn-outline-secondary" id="btnZoomMenos" aria-label="Reducir zoom"><i class="bi bi-zoom-out" aria-hidden="true"></i></button>
            <button class="btn btn-outline-secondary" id="btnZoomMas" aria-label="Ampliar zoom"><i class="bi bi-zoom-in" aria-hidden="true"></i></button>
            <a class="btn btn-outline-dark" href="{{ route('actas.print', $acta) }}" target="_blank"><i class="bi bi-printer"></i> Imprimir</a>
        </div>
    </div>
    <div class="card-body bg-secondary-subtle text-center pdf-document-stage" style="min-height: 70vh;" tabindex="0" role="region" aria-label="Documento PDF. Puede desplazarse para ver toda la página.">
        <canvas id="visorPdfCanvas" class="shadow bg-white"></canvas>
        <p class="text-muted small mt-2" id="mensajeVisor">Cargando documento…</p>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script>
const urlPdf = @json(route('actas.pdf', $acta));
pdfjsLib.GlobalWorkerOptions.workerSrc = '{{ asset('vendor/pdfjs/pdf.worker.min.js') }}';

let pdfDoc = null, paginaActual = 1, escala = 1.2;
const canvas = document.getElementById('visorPdfCanvas');
const ctx = canvas.getContext('2d');

function renderizarPagina(numero) {
    if (!pdfDoc) return;
    pdfDoc.getPage(numero).then(function (pagina) {
        const viewport = pagina.getViewport({ scale: escala });
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        pagina.render({ canvasContext: ctx, viewport: viewport });
        document.getElementById('indicadorPagina').textContent = `Página ${numero} / ${pdfDoc.numPages}`;
        document.getElementById('mensajeVisor').textContent = '';
    });
}

pdfjsLib.getDocument(urlPdf).promise.then(function (doc) {
    pdfDoc = doc;
    renderizarPagina(paginaActual);
}).catch(function () {
    document.getElementById('mensajeVisor').textContent = 'No se pudo cargar el documento PDF.';
});

document.getElementById('btnPaginaAnterior').addEventListener('click', function () {
    if (paginaActual > 1) { paginaActual--; renderizarPagina(paginaActual); }
});
document.getElementById('btnPaginaSiguiente').addEventListener('click', function () {
    if (pdfDoc && paginaActual < pdfDoc.numPages) { paginaActual++; renderizarPagina(paginaActual); }
});
document.getElementById('btnZoomMas').addEventListener('click', function () {
    escala = Math.min(escala + 0.2, 3); renderizarPagina(paginaActual);
});
document.getElementById('btnZoomMenos').addEventListener('click', function () {
    escala = Math.max(escala - 0.2, 0.4); renderizarPagina(paginaActual);
});
</script>
@endpush
