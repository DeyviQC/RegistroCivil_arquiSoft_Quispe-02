<?php

/**
 * Coverage gate for CI: fails when line coverage in a Clover report drops below the minimum.
 * Usage: php tests/cobertura-minima.php <clover.xml> <minimum percent>
 */
[$script, $archivo, $minimo] = $argv + [null, null, null];
if (! $archivo || ! is_numeric($minimo) || ! is_readable($archivo)) {
    fwrite(STDERR, "Uso: php {$script} <clover.xml> <porcentaje mínimo>\n");
    exit(2);
}
$metricas = simplexml_load_file($archivo)->project->metrics;
$total = (int) $metricas['statements'];
$cubiertas = (int) $metricas['coveredstatements'];
$porcentaje = $total ? 100 * $cubiertas / $total : 100.0;
printf("Cobertura de líneas: %.2f %% (%d de %d) · mínimo exigido: %.2f %%\n", $porcentaje, $cubiertas, $total, $minimo);
if ($porcentaje + 1e-9 < (float) $minimo) {
    fwrite(STDERR, "La cobertura está por debajo del mínimo.\n");
    exit(1);
}
