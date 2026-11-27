<?php

/**
 * EQ-08 — compares an original installation with one restored from a backup.
 * Never prints secrets: keys are compared by their SHA-256 fingerprints only.
 *
 * php tests/recuperacion/verificar-restauracion.php <json with the paths and databases>
 *   {"mysql": {...}, "base_original": "...", "base_restaurada": "...", "archivos_original": "...",
 *    "archivos_restaurados": "...", "app_key_original": "...", "clave_cifrada": ".../app-key.enc", "frase": "..."}
 */
require __DIR__.'/../../vendor/autoload.php';

use App\Console\Commands\CrearRespaldo;
use Illuminate\Encryption\Encrypter;

// PowerShell 5.1 writes UTF-8 with a BOM; json_decode does not accept it.
$cfg = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($argv[1] ?? '')), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO("mysql:host={$cfg['mysql']['host']};port={$cfg['mysql']['port']};charset=utf8mb4", $cfg['mysql']['usuario'], $cfg['mysql']['clave'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$res = ['tablas' => [], 'archivos' => [], 'clave' => [], 'datos_cifrados' => []];

// 1. Every table: same row count and same content checksum.
$tablas = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = ".$pdo->quote($cfg['base_original'])." AND table_type = 'BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$iguales = 0;
foreach ($tablas as $t) {
    $fila = [];
    foreach (['original' => $cfg['base_original'], 'restaurada' => $cfg['base_restaurada']] as $lado => $base) {
        $fila["filas_$lado"] = (int) $pdo->query("SELECT COUNT(*) FROM `$base`.`$t`")->fetchColumn();
        $fila["checksum_$lado"] = (string) $pdo->query("CHECKSUM TABLE `$base`.`$t`")->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    // Sessions and the cache keep changing while the original is in use; they are not business data.
    $fila['dato_de_negocio'] = ! in_array($t, ['sessions', 'cache', 'cache_locks', 'jobs'], true);
    $fila['igual'] = $fila['filas_original'] === $fila['filas_restaurada'] && $fila['checksum_original'] === $fila['checksum_restaurada'];
    if ($fila['igual']) $iguales++;
    $res['tablas'][$t] = $fila;
}
$negocio = array_filter($res['tablas'], fn ($f) => $f['dato_de_negocio']);
$res['resumen_tablas'] = ['total' => count($tablas), 'identicas' => $iguales,
    'de_negocio' => count($negocio), 'de_negocio_identicas' => count(array_filter($negocio, fn ($f) => $f['igual']))];

// 2. Every private document: present in the restored copy with the same SHA-256.
$origen = rtrim($cfg['archivos_original'], '/\\');
$destino = rtrim($cfg['archivos_restaurados'], '/\\');
$total = 0; $identicos = 0; $faltan = []; $distintos = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origen, FilesystemIterator::SKIP_DOTS));
foreach ($it as $archivo) {
    if (! $archivo->isFile()) continue;
    $total++;
    $relativo = substr($archivo->getPathname(), strlen($origen) + 1);
    $copia = $destino.DIRECTORY_SEPARATOR.$relativo;
    if (! is_file($copia)) { $faltan[] = $relativo; continue; }
    hash_file('sha256', $archivo->getPathname()) === hash_file('sha256', $copia) ? $identicos++ : $distintos[] = $relativo;
}
$res['archivos'] = ['revisados' => $total, 'identicos' => $identicos, 'faltan' => array_slice($faltan, 0, 20), 'distintos' => array_slice($distintos, 0, 20)];

// 3. APP_KEY recovered from the encrypted copy with the passphrase (fingerprints only).
$recuperada = CrearRespaldo::descifrarClave((string) file_get_contents($cfg['clave_cifrada']), $cfg['frase']);
$res['clave'] = ['recuperada' => $recuperada !== null, 'coincide_con_la_original' => $recuperada !== null && hash_equals(hash('sha256', $cfg['app_key_original']), hash('sha256', $recuperada)),
    'frase_incorrecta_rechazada' => CrearRespaldo::descifrarClave((string) file_get_contents($cfg['clave_cifrada']), $cfg['frase'].'x') === null];

// 4. With the recovered key, every citizen request can be decrypted and its DNI index rebuilt.
if ($recuperada) {
    $clave = str_starts_with($recuperada, 'base64:') ? base64_decode(substr($recuperada, 7)) : $recuperada;
    $cifrador = new Encrypter($clave, 'aes-256-cbc');
    $ok = 0; $fallos = 0; $indiceOk = 0; $conDni = 0;
    foreach ($pdo->query("SELECT datos, dni_hash FROM `{$cfg['base_restaurada']}`.tramites_publicos WHERE datos IS NOT NULL") as $fila) {
        try {
            $datos = json_decode($cifrador->decryptString($fila['datos']), true, 512, JSON_THROW_ON_ERROR);
            $ok++;
            $dni = $datos['dni'] ?? null;
            if ($dni && preg_match('/^[0-9]{8}$/D', $dni)) {
                $conDni++;
                if (hash_equals((string) $fila['dni_hash'], hash_hmac('sha256', 'dni:'.$dni, $recuperada))) $indiceOk++;
            }
        } catch (Throwable) {
            $fallos++;
        }
    }
    $res['datos_cifrados'] = ['solicitudes_descifradas' => $ok, 'fallidas' => $fallos, 'con_dni' => $conDni, 'indice_dni_correcto' => $indiceOk];
}

$res['aprobado'] = $res['resumen_tablas']['de_negocio'] === $res['resumen_tablas']['de_negocio_identicas']
    && $res['archivos']['revisados'] === $res['archivos']['identicos']
    && $res['clave']['coincide_con_la_original'] && $res['clave']['frase_incorrecta_rechazada']
    && ($res['datos_cifrados']['fallidas'] ?? 1) === 0
    && ($res['datos_cifrados']['con_dni'] ?? 0) === ($res['datos_cifrados']['indice_dni_correcto'] ?? -1);
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($res['aprobado'] ? 0 : 1);
