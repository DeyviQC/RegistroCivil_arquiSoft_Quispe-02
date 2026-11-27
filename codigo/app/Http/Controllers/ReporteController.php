<?php
namespace App\Http\Controllers;

use App\Models\Acta;
use App\Models\Auditoria;
use App\Models\User;
use App\Http\Requests\ReporteRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    private function datos(ReporteRequest $request): array
    {
        $this->authorize('viewAny', Acta::class);
        $f = $request->validated();
        $reporte = $f['reporte'] ?? 'actas';
        $titulos = array_map(fn ($config) => $config['titulo'], ReporteRequest::REPORTES);
        $filas = [];
        if ($reporte === 'actividad') {
            $this->authorize('viewAny', Auditoria::class);
            $q = Auditoria::with('usuario');
            if (! empty($f['usuario_id'])) { $q->where('usuario_id', $f['usuario_id']); }
            foreach (['fecha_desde' => '>=', 'fecha_hasta' => '<='] as $campo => $op) {
                if (! empty($f[$campo])) { $limite = \Carbon\Carbon::parse($f[$campo], 'America/Lima'); if ($op === '<=') { $limite->addDay(); } $q->where('created_at', $op === '<=' ? '<' : '>=', $limite->utc()); }
            }
            $cabeceras = ['Fecha y hora', 'Usuario', 'Acción', 'Módulo', 'Descripción'];
            foreach ($q->orderByDesc('created_at')->orderByDesc('id')->get() as $evento) {
                $filas[] = [$evento->created_at->timezone('America/Lima')->format('d/m/Y H:i'), $evento->usuario?->name ?? 'Sistema', $evento->accion, $evento->modulo, $evento->descripcion ?? ''];
            }
        } else {
            $q = Acta::with(['nacimiento', 'defuncion', 'matrimonio', 'usuario']);
            foreach (['tipo', 'anio', 'sobre', 'correlativo', 'usuario_id'] as $campo) {
                if (isset($f[$campo]) && $f[$campo] !== '') { $q->where($campo, $f[$campo]); }
            }
            foreach (['fecha_desde' => '>=', 'fecha_hasta' => '<='] as $campo => $op) {
                if (! empty($f[$campo])) { $q->whereDate('fecha_acta', $op, $f[$campo]); }
            }
            if ($reporte === 'resumen') { $q->whereMonth('fecha_acta', $f['mes']); }
            $grupos = [];
            $cabeceras = ['Orden', 'N° acta', 'Tipo', 'Fecha registro', 'Apellidos y nombres', 'DNI', 'Sobre', 'Correlativo', 'PDF', 'Usuario'];
            foreach ($q->orderByDesc('fecha_acta')->orderByDesc('id')->get() as $acta) {
                $disponible = $acta->pdf_ruta && $acta->pdf_tamano > 0 && Storage::disk('local')->exists($acta->pdf_ruta);
                if ($reporte === 'sobres') {
                    $clave = $acta->tipo.'|'.$acta->sobre;
                    $grupos[$clave] ??= ['tipo'=>$acta->tipo,'sobre'=>$acta->sobre,'total'=>0,'con'=>0,'sin'=>0,'posiciones'=>[]];
                    $grupos[$clave]['total']++;
                    $grupos[$clave][$disponible?'con':'sin']++;
                    if ($acta->correlativo >= 1 && $acta->correlativo <= 25) {
                        $grupos[$clave]['posiciones'][$acta->correlativo] = true;
                    }
                    continue;
                }
                if ($reporte === 'pendientes' && $disponible) { continue; }
                if (in_array($reporte, ['resumen','anual'])) {
                    $clave = $acta->tipo;
                    $grupos[$clave] ??= ['NACIMIENTO'=>0,'DEFUNCION'=>0,'MATRIMONIO'=>0,'con'=>0,'sin'=>0];
                    $grupos[$clave][$acta->tipo]++;
                    $grupos[$clave][$disponible ? 'con' : 'sin']++;
                    continue;
                }
                $d = $acta->detalle();
                $filas[] = [$acta->orden_tipo, $acta->numero_acta, $acta->tipo, $acta->fecha_acta->format('d/m/Y'), $d?->apellido_nombre ?? '', $d?->getAttribute('dni_no_consignado') ?'No consignado en el acta' : ($d?->dni ?? ''), $acta->sobre, $acta->correlativo, $disponible ? 'Disponible' : 'Pendiente', $acta->usuario?->name ?? ''];
            }
            if ($reporte === 'sobres') {
                $cabeceras = ['Tipo de acta','Sobre','Actas','Con PDF','Sin PDF','Espacios libres','Ocupación'];
                usort($grupos, fn ($a,$b) => strcmp($a['tipo'],$b['tipo']) ?: strnatcmp((string)$a['sobre'],(string)$b['sobre']));
                foreach ($grupos as $g) {
                    $ocupados = count($g['posiciones']);
                    $estado = $g['total'] > 25 ? 'Histórico: más de 25 actas' : ($ocupados===25?'Completo':'Disponible');
                    $filas[] = [$g['tipo'],$g['sobre'] ?: 'Sin asignar',$g['total'],$g['con'],$g['sin'],25-$ocupados,$estado];
                }
            }
            if (in_array($reporte, ['resumen','anual'])) {
                $vacio=['NACIMIENTO'=>0,'DEFUNCION'=>0,'MATRIMONIO'=>0,'con'=>0,'sin'=>0];

                    $cabeceras=['Tipo de acta','Total','Con PDF','Sin PDF'];
                    foreach (!empty($f['tipo'])?[$f['tipo']]:['NACIMIENTO','DEFUNCION','MATRIMONIO'] as $tipo) {
                        $g=$grupos[$tipo]??$vacio;
                        $filas[]=[$tipo,$g['con']+$g['sin'],$g['con'],$g['sin']];
                    }
            }
        }
        $metricas = ['total'=>0,'con'=>0,'sin'=>0];
        if (in_array($reporte,['resumen','anual'])) { foreach ($filas as $fila) { $metricas['total'] += $fila[1]; $metricas['con'] += $fila[2]; $metricas['sin'] += $fila[3]; } }
        return ['metricas'=>$metricas,'configuracion'=>ReporteRequest::REPORTES, 'titulo' => $titulos[$reporte], 'reporte' => $reporte, 'filtros' => $f, 'cabeceras' => $cabeceras, 'filas' => $filas];
    }

    public function index(ReporteRequest $request)
    {
        $datos = $this->datos($request);
        $page = (int) $request->input('page', 1);
        $datos['total'] = count($datos['filas']);
        $datos['registros'] = new LengthAwarePaginator(array_slice($datos['filas'], ($page - 1) * 25, 25), $datos['total'], 25, $page, ['path' => $request->url(), 'query' => $request->except('page')]);
        $datos['usuarios'] = User::orderBy('name')->get(['id', 'name']);
        return view('reportes.index', $datos);
    }

    public function pdf(ReporteRequest $request)
    {
        $datos = $this->datos($request);
        $pdf = Pdf::loadView('reportes.pdf', $datos)->setPaper('a4', 'landscape');
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(690, 575, 'Página {PAGE_NUM} de {PAGE_COUNT}', $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 8);
        return $pdf->download('reporte-'.$datos['reporte'].'.pdf');
    }

    public function excel(ReporteRequest $request)
    {
        $datos = $this->datos($request);
        $ruta = tempnam(sys_get_temp_dir(), 'reporte_');
        $zip = new \ZipArchive();
        try {
            if ($zip->open($ruta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { throw new \RuntimeException('No se pudo crear Excel.'); }
            $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/><sheet name="Información" sheetId="2" r:id="rId2"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
            $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="10" width="24" customWidth="1"/></cols><sheetData>';
            foreach (array_merge([$datos['cabeceras']], $datos['filas']) as $i => $fila) {
                $xml .= '<row r="'.($i+1).'">';
                foreach ($fila as $j => $valor) {
                    $ref = chr(65+$j).($i+1);
                    if (is_int($valor)) { $xml .= '<c r="'.$ref.'"><v>'.$valor.'</v></c>'; }
                    else { $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) $valor, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>'; }
                }
                $xml .= '</row>';
            }
            $xml .= '</sheetData><autoFilter ref="A1:'.chr(64+count($datos['cabeceras'])).(count($datos['filas'])+1).'"/></worksheet>';
            $info = [['Reporte', $datos['titulo']], ['Descripción', ReporteRequest::REPORTES[$datos['reporte']]['descripcion']], ['Generado (Lima)', now('America/Lima')->format('d/m/Y H:i')]];
            foreach ($datos['filtros'] as $campo=>$valor) {
                if ($valor !== null && $valor !== '' && !in_array($campo,['page','reporte'])) { $info[]=[str_replace('_',' ',$campo),(string)$valor]; }
            }
            if(in_array($datos['reporte'],['resumen','anual'])) { foreach(['total'=>'Total actas','con'=>'Con PDF','sin'=>'Pendientes de PDF'] as $k=>$v){$info[]=[$v,(string)$datos['metricas'][$k]];} }
            $meta='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="28" customWidth="1"/><col min="2" max="2" width="100" customWidth="1"/></cols><sheetData>';
            foreach($info as $i=>$fila){$meta.='<row r="'.($i+1).'">';foreach($fila as $j=>$v){$meta.='<c r="'.chr(65+$j).($i+1).'" t="inlineStr"><is><t>'.htmlspecialchars($v,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';}$meta.='</row>';}
            $zip->addFromString('xl/worksheets/sheet2.xml',$meta.'</sheetData></worksheet>');
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml); $zip->close();
            return response()->download($ruta, 'reporte-'.$datos['reporte'].'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
        } catch (\Throwable $e) { if (is_file($ruta)) { unlink($ruta); } throw $e; }
    }
}
