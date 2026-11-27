<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActaRequest;
use App\Http\Requests\StoreDefuncionRequest;
use App\Http\Requests\StoreMatrimonioRequest;
use App\Http\Requests\StoreNacimientoRequest;
use App\Models\Acta;
use App\Services\ActaService;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ActaController extends Controller
{
    public function __construct(
        private readonly ActaService $actaService,
        private readonly AuditoriaService $auditoriaService,
    ) {
    }

    /**
     * Búsqueda y listado de actas (Sección 20).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Acta::class);

        $filtros = $request->only([
            'numero_acta', 'anio', 'tipo', 'dni', 'nombre',
            'sobre', 'correlativo', 'fecha_desde', 'fecha_hasta',
        ]);

        $actas = $this->actaService->buscar($filtros);

        $incidencias = app(\App\Services\IncidenciaService::class)->todas()
            ->whereIn('estado', \App\Services\IncidenciaService::ESTADOS_VISIBLES)->keyBy('acta_id');
        return view('actas.index', compact('actas', 'filtros', 'incidencias'));
    }

    public function create()
    {
        $this->authorize('create', Acta::class);

        $siguientes = \Illuminate\Support\Facades\DB::table('acta_secuencias')->get()->mapWithKeys(function ($fila) {
            $orden = $fila->ultimo + 1;
            $ultimo = Acta::where('tipo', $fila->tipo)->orderByDesc('orden_tipo')->first();
            $sobre = $ultimo ? (int) $ultimo->sobre : 1;
            $correlativo = $ultimo ? $ultimo->correlativo + 1 : 1;
            if ($correlativo > 25) { $sobre++; $correlativo = 1; }
            return [$fila->tipo => ['orden' => $orden, 'sobre' => max(1, $sobre), 'correlativo' => $correlativo]];
        });

        return view('actas.create', compact('siguientes'));
    }

    /**
     * Registra el acta completa: valida el request "envoltorio" (StoreActaRequest)
     * y además el sub-formulario específico según el tipo, en la misma petición.
     */
    public function store(StoreActaRequest $request)
    {
        $this->authorize('create', Acta::class);

        $datosActa = $request->validated();
        $datosActa['anio'] = \Carbon\Carbon::parse($datosActa['fecha_acta'])->year;
        $tipo = $datosActa['tipo'];

        // Validamos el sub-formulario correspondiente al tipo de acta,
        // reutilizando las reglas de cada FormRequest sin necesitar rutas separadas.
        $reglasDetalle = match ($tipo) {
            Acta::TIPO_NACIMIENTO => (new StoreNacimientoRequest())->rules(),
            Acta::TIPO_MATRIMONIO => (new StoreMatrimonioRequest())->rules(),
            Acta::TIPO_DEFUNCION => (new StoreDefuncionRequest())->rules(),
            default => abort(422, 'Tipo de acta no válido.'),
        };

        $datosDetalle = array_intersect_key($datosActa, $reglasDetalle);
        if (in_array($tipo, [Acta::TIPO_NACIMIENTO, Acta::TIPO_DEFUNCION])) {
            $datosDetalle['dni_no_consignado'] = $request->boolean('dni_no_consignado');
            if ($datosDetalle['dni_no_consignado']) { $datosDetalle['dni'] = null; }
        }

        try {
            $acta = $this->actaService->registrar(
                $datosActa,
                $datosDetalle,
                $request->file('pdf'), // PDF obligatorio para todos los tipos
                $request->user()
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $mensaje = 'Registro guardado con su PDF adjunto.';

        return redirect()->route('actas.show', $acta)->with('exito', $mensaje);
    }

    /**
     * Adjunta el PDF firmado/escaneado a una Defunción que estaba en BORRADOR,
     * lo que la deja bloqueada (REGISTRADA) de forma permanente.
     */
    public function completarPdf(Request $request, Acta $acta)
    {
        $this->authorize('completar', $acta);

        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:' . (int) config('actas.pdf_max_kb', 10240)],
        ]);

        try {
            $this->actaService->completarPdf($acta, $request->file('pdf'), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('actas.show', $acta)
            ->with('exito', 'PDF adjuntado correctamente. El acta queda bloqueada y disponible únicamente para consulta.');
    }

    public function show(Acta $acta)
    {
        $this->authorize('view', $acta);

        $acta->load(['nacimiento', 'matrimonio', 'defuncion', 'usuario']);

        $this->auditoriaService->registrar(
            AuditoriaService::CONSULTAR_ACTA,
            'Registro Civil',
            $acta->id,
            "Consulta del acta {$acta->tipo} N° {$acta->numero_acta}/{$acta->anio}."
        );

        $incidencias = app(\App\Services\IncidenciaService::class)->todas()->where('acta_id', $acta->id);
        return view('actas.show', compact('acta', 'incidencias'));
    }

    /**
     * Vista optimizada para impresión (Sección 23). Sin sidebar/menú.
     */
    public function imprimir(Acta $acta)
    {
        $this->authorize('view', $acta);

        $acta->load(['nacimiento', 'matrimonio', 'defuncion', 'usuario']);

        $this->auditoriaService->registrar(
            AuditoriaService::IMPRIMIR_PDF,
            'Registro Civil',
            $acta->id,
            "Impresión del acta {$acta->tipo} N° {$acta->numero_acta}/{$acta->anio}."
        );

        return view('pdf.imprimir', compact('acta'));
    }
    /**
     * Bloqueo explícito: nunca se expone update/destroy en las rutas
     * (ver routes/web.php), pero además la Policy lo niega siempre.
     * Estos métodos existen solo para devolver un 403 claro si alguien
     * intenta forzar la URL.
     */
    public function edit(Acta $acta)
    {
        $this->authorize('update', $acta);
    }

    public function destroy(Acta $acta)
    {
        $this->authorize('delete', $acta);
    }
}
