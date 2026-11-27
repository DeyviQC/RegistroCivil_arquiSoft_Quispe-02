<?php

/**
 * Builds the SVG charts of sprint-2/resultados from the k6 summaries in sprint-2/evidencias, so every number
 * in the charts comes from the evidence files (nothing typed by hand).
 *
 *   php tests/carga/graficos-progresiva.php
 *
 * Charts follow light/dark mode through prefers-color-scheme (GitHub shows SVGs as images and honours it).
 */
$raiz = dirname(__DIR__, 3);
$evidencias = "$raiz/sprint-2/evidencias";
$salida = "$raiz/sprint-2/resultados/graficos";
@mkdir($salida, 0777, true);

$leer = fn (string $f) => json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents("$evidencias/$f")), true, 512, JSON_THROW_ON_ERROR);
$series = [
    // [label, file, css class] — the final run first (slot 1), the first run second (slot 2), fixed order.
    ['Corrida final (con correcciones)', 'eq-progresiva-final-resumen.json', 's1'],
    ['Primera corrida', 'eq-progresiva-20php-resumen.json', 's2'],
];
$datos = [];
foreach ($series as [$nombre, $archivo, $clase]) {
    $datos[] = ['nombre' => $nombre, 'clase' => $clase, 'niveles' => $leer($archivo)['niveles']];
}
$niveles = array_column($datos[0]['niveles'], 'nivel');

function esc(string $t): string { return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function num(float $v, int $d = 0): string { return number_format($v, $d, ',', ' '); }

/**
 * Line chart, one categorical x position per load level, one y scale.
 *
 * @param  callable(array): float  $valor  value for one level row
 */
function grafico(string $archivo, string $titulo, string $subtitulo, array $datos, array $niveles, callable $valor,
    float $maxY, float $paso, callable $etiquetaY, float $meta, string $textoMeta, callable $etiquetaPunto): void
{
    $an = 760; $al = 400; $izq = 64; $der = 150; $sup = 92; $inf = 52;
    $ancho = $an - $izq - $der; $alto = $al - $sup - $inf;
    $x = fn (int $i) => $izq + $ancho * ($i / (count($niveles) - 1));
    $y = fn (float $v) => $sup + $alto * (1 - min($v, $maxY) / $maxY);

    $svg = [];
    $svg[] = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $an $al' width='$an' height='$al' role='img' aria-labelledby='t d'>";
    $svg[] = '<title id="t">'.esc($titulo).'</title><desc id="d">'.esc($subtitulo).'</desc>';
    $svg[] = '<style>
  svg{font-family:-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;font-variant-numeric:tabular-nums}
  .fondo{fill:#fcfcfb}.t1{fill:#0b0b0b}.t2{fill:#52514e}.t3{fill:#898781}
  .rejilla{stroke:#e1e0d9;stroke-width:1}.base{stroke:#c3c2b7;stroke-width:1}
  .meta{stroke:#52514e;stroke-width:1.5;stroke-dasharray:6 4}
  .s1{stroke:#2a78d6;fill:#2a78d6}.s2{stroke:#eb6834;fill:#eb6834}
  .punto{stroke:#fcfcfb;stroke-width:2}
  @media (prefers-color-scheme:dark){
    .fondo{fill:#1a1a19}.t1{fill:#f0efec}.t2{fill:#c3c2b7}
    .rejilla{stroke:#2c2c2a}.base{stroke:#383835}.meta{stroke:#c3c2b7}
    .s1{stroke:#3987e5;fill:#3987e5}.s2{stroke:#d95926;fill:#d95926}.punto{stroke:#1a1a19}
  }
  polyline.linea{fill:none;stroke-width:2;stroke-linejoin:round;stroke-linecap:round}polyline.s2.linea{stroke-dasharray:5 4}
</style>';
    $svg[] = "<rect class='fondo' width='$an' height='$al' rx='8'/>";
    $svg[] = "<text class='t1' x='$izq' y='30' font-size='17' font-weight='600'>".esc($titulo).'</text>';
    $svg[] = "<text class='t2' x='$izq' y='50' font-size='12.5'>".esc($subtitulo).'</text>';
    // Legend: one row above the plot (≥2 series), mark + text in ink colours.
    $lx = $izq;
    foreach ($datos as $s) {
        $dash = $s['clase'] === 's2' ? " stroke-dasharray='5 4'" : '';
        $svg[] = "<line class='{$s['clase']}' x1='$lx' y1='70' x2='".($lx + 22)."' y2='70' stroke-width='2'$dash/>";
        $svg[] = "<circle class='{$s['clase']} punto' cx='".($lx + 11)."' cy='70' r='4'/>";
        $svg[] = "<text class='t2' x='".($lx + 30)."' y='74' font-size='12'>".esc($s['nombre']).'</text>';
        $lx += 30 + 7.2 * mb_strlen($s['nombre']) + 22;
    }
    // Grid and y axis labels (recessive).
    for ($v = 0; $v <= $maxY + 1e-9; $v += $paso) {
        $yy = round($y($v), 1);
        $svg[] = "<line class='".($v == 0 ? 'base' : 'rejilla')."' x1='$izq' y1='$yy' x2='".($izq + $ancho)."' y2='$yy'/>";
        $svg[] = "<text class='t3' x='".($izq - 8)."' y='".($yy + 4)."' font-size='11' text-anchor='end'>".esc($etiquetaY($v)).'</text>';
    }
    foreach ($niveles as $i => $n) {
        $svg[] = "<text class='t3' x='".round($x($i), 1)."' y='".($sup + $alto + 20)."' font-size='11' text-anchor='middle'>".num($n).'</text>';
    }
    $svg[] = "<text class='t3' x='".($izq + $ancho / 2)."' y='".($al - 10)."' font-size='11.5' text-anchor='middle'>Usuarios simultáneos</text>";
    // Goal line with its label at the right end.
    if ($textoMeta !== '') {
        $ym = round($y($meta), 1);
        $svg[] = "<line class='meta' x1='$izq' y1='$ym' x2='".($izq + $ancho)."' y2='$ym'/>";
        $svg[] = "<text class='t2' x='".($izq + $ancho + 8)."' y='".($ym + 4)."' font-size='11.5' font-weight='600'>".esc($textoMeta).'</text>';
    }
    // Series: final run drawn last so it sits on top.
    foreach (array_reverse($datos) as $s) {
        $pts = [];
        foreach ($s['niveles'] as $i => $f) $pts[] = round($x($i), 1).','.round($y($valor($f)), 1);
        $svg[] = "<polyline class='{$s['clase']} linea' points='".implode(' ', $pts)."'/>";
        foreach ($s['niveles'] as $i => $f) {
            $v = $valor($f);
            $svg[] = "<circle class='{$s['clase']} punto' cx='".round($x($i), 1)."' cy='".round($y($v), 1)."' r='4.5'><title>"
                .esc($s['nombre'].' · '.num($f['nivel']).' usuarios: '.$etiquetaPunto($v)).'</title></circle>';
        }
        // Direct label at the line end (2 series ≤ 4: legend + direct labels).
        $ult = end($s['niveles']);
        $svg[] = "<text class='t2' x='".($izq + $ancho + 8)."' y='".round($y($valor($ult)) + 4, 1)."' font-size='11'>"
            .esc(($s['clase'] === 's1' ? 'Final ' : 'Primera ').$etiquetaPunto($valor($ult))).'</text>';
    }
    // Value labels only where the final run crosses the goal (selective, not every point).
    $f1 = $datos[0]['niveles'];
    foreach ($f1 as $i => $f) {
        $siguiente = $f1[$i + 1] ?? null;
        if ($valor($f) <= $meta && $siguiente && $valor($siguiente) > $meta) {
            $svg[] = "<text class='t1' x='".round($x($i), 1)."' y='".round($y($valor($f)) - 12, 1)."' font-size='11.5' font-weight='600' text-anchor='middle'>"
                .esc($etiquetaPunto($valor($f))).'</text>';
        }
    }
    $svg[] = '</svg>';
    file_put_contents($archivo, implode("\n", $svg)."\n");
}

grafico("$salida/p95-por-nivel.svg", 'Tiempo de respuesta (p95) por nivel de carga',
    'k6 · 50 000 actas · 10 personas del personal fijas · meta EQ-01: p95 ≤ 2 s', $datos, $niveles,
    fn ($f) => $f['p95_ms'] / 1000, 14, 2, fn ($v) => num($v).' s', 2, 'Meta 2 s',
    fn ($v) => num($v, $v < 10 ? 2 : 1).' s');

grafico("$salida/fallidas-por-nivel.svg", 'Peticiones fallidas por nivel de carga',
    'Conexiones rechazadas o cerradas por el servidor saturado · meta: menos del 1 %', $datos, $niveles,
    fn ($f) => $f['fallidas'] * 100, 40, 10, fn ($v) => num($v).' %', 1, 'Meta 1 %',
    fn ($v) => num($v, $v < 10 ? 2 : 1).' %');

grafico("$salida/peticiones-por-segundo.svg", 'Peticiones atendidas por segundo',
    'Capacidad real del servidor en cada nivel (más es mejor)', $datos, $niveles,
    fn ($f) => $f['por_segundo'], 100, 20, fn ($v) => num($v), 0, '',
    fn ($v) => num($v, 1).' pet/s');

echo "Gráficos generados en $salida\n";
