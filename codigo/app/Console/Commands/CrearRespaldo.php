<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Respaldo diario:
 *  - Volcado comprimido de la base de datos (se conservan N días).
 *  - Copia espejo incremental de storage/app/private (PDFs de actas, firmas,
 *    fotos). Las actas son inmutables, así que solo se copian archivos nuevos o
 *    cambiados y nunca se borra nada del espejo.
 */
class CrearRespaldo extends Command
{
    protected $signature = 'respaldo:crear {--solo-archivos : No volcar la base de datos}';

    protected $description = 'Respalda la base de datos y los documentos privados en la carpeta configurada.';

    public function handle(): int
    {
        $destino = rtrim((string) config('respaldo.ruta'), '/\\');
        $origen = storage_path('app/private');

        if ($this->dentroDe($destino, $origen)) {
            $this->error('RESPALDO_RUTA no puede estar dentro de storage/app/private.');
            return self::FAILURE;
        }
        File::ensureDirectoryExists($destino.'/base-datos');
        File::ensureDirectoryExists($destino.'/archivos');

        $ok = true;
        if (! $this->option('solo-archivos')) {
            $ok = $this->respaldarBaseDatos($destino.'/base-datos');
            $this->limpiarVolcadosAntiguos($destino.'/base-datos');
        }
        $ok = $this->respaldarClave($destino) && $ok;
        [$copiados, $total] = $this->espejo($origen, $destino.'/archivos');
        $this->info("Archivos: {$copiados} copiados de {$total} revisados.");

        File::put($destino.'/ultimo-respaldo.txt', now()->toIso8601String().($ok ? ' OK' : ' CON ERRORES').PHP_EOL);

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function respaldarBaseDatos(string $carpeta): bool
    {
        $conexion = config('database.default');
        $cfg = config("database.connections.{$conexion}");
        $archivo = $carpeta.'/'.$conexion.'-'.now()->format('Ymd-His');

        if (($cfg['driver'] ?? null) === 'sqlite') {
            $db = $cfg['database'] ?? '';
            if ($db === ':memory:' || ! is_file($db)) {
                $this->warn('Base SQLite en memoria o inexistente: no hay nada que volcar.');
                return true;
            }
            return $this->comprimir($db, $archivo.'.sqlite.gz', false);
        }

        if (! in_array($cfg['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('Driver de base de datos no soportado para respaldo: '.($cfg['driver'] ?? '?'));
            return false;
        }

        $temporal = $archivo.'.sql';
        $proceso = new Process([
            config('respaldo.mysqldump'),
            '--host='.$cfg['host'], '--port='.$cfg['port'], '--user='.$cfg['username'],
            '--single-transaction', '--quick', '--routines', '--triggers',
            '--default-character-set=utf8mb4', '--result-file='.$temporal, $cfg['database'],
        ], null, ['MYSQL_PWD' => (string) $cfg['password']], null, 3600); // password never on the command line
        $proceso->run();

        if (! $proceso->isSuccessful() || ! is_file($temporal) || filesize($temporal) === 0) {
            File::delete($temporal);
            $this->error('mysqldump falló: '.trim($proceso->getErrorOutput() ?: 'sin detalle'));
            return false;
        }

        return $this->comprimir($temporal, $archivo.'.sql.gz', true);
    }

    /**
     * Citizen data is encrypted with APP_KEY: a database dump is useless without it.
     * Keep a copy next to the dump, encrypted with a passphrase that never lives in the backup.
     */
    private function respaldarClave(string $destino): bool
    {
        $frase = (string) config('respaldo.frase');
        if (strlen($frase) < 12) {
            $this->error('Configura RESPALDO_FRASE (mínimo 12 caracteres) para respaldar la APP_KEY. Sin ella el respaldo de la base es ilegible.');
            return false;
        }
        $sal = random_bytes(16);
        $iv = random_bytes(12);
        $llave = hash_pbkdf2('sha256', $frase, $sal, 200000, 32, true);
        $cifrada = openssl_encrypt((string) config('app.key'), 'aes-256-gcm', $llave, OPENSSL_RAW_DATA, $iv, $tag);
        File::put($destino.'/app-key.enc', 'v1:'.base64_encode($sal.$iv.$tag.$cifrada).PHP_EOL);
        $this->info('APP_KEY respaldada cifrada en app-key.enc.');
        return true;
    }

    /** Inverse of respaldarClave(), used by "respaldo:clave". */
    public static function descifrarClave(string $contenido, string $frase): ?string
    {
        if (!str_starts_with(trim($contenido), 'v1:')) return null;
        $bin = base64_decode(substr(trim($contenido), 3), true);
        if ($bin === false || strlen($bin) < 45) return null;
        $llave = hash_pbkdf2('sha256', $frase, substr($bin, 0, 16), 200000, 32, true);
        $clave = openssl_decrypt(substr($bin, 44), 'aes-256-gcm', $llave, OPENSSL_RAW_DATA, substr($bin, 16, 12), substr($bin, 28, 16));
        return $clave === false ? null : $clave;
    }

    private function comprimir(string $origen, string $destino, bool $borrarOrigen): bool
    {
        $in = fopen($origen, 'rb');
        $out = gzopen($destino, 'wb6');
        if (! $in || ! $out) {
            $this->error('No se pudo escribir el volcado comprimido.');
            return false;
        }
        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 1024));
        }
        fclose($in);
        gzclose($out);
        if ($borrarOrigen) {
            File::delete($origen);
        }
        $this->info('Base de datos respaldada en '.basename($destino).'.');
        return true;
    }

    private function limpiarVolcadosAntiguos(string $carpeta): void
    {
        $limite = now()->subDays(max(1, (int) config('respaldo.dias_base_datos', 14)))->getTimestamp();
        foreach (File::files($carpeta) as $archivo) {
            if ($archivo->getMTime() < $limite) {
                File::delete($archivo->getPathname());
            }
        }
    }

    /** @return array{int, int} [copiados, revisados] */
    private function espejo(string $origen, string $destino): array
    {
        if (! is_dir($origen)) {
            return [0, 0];
        }
        $copiados = 0; $total = 0;
        foreach (File::allFiles($origen, true) as $archivo) {
            $total++;
            $relativo = $archivo->getRelativePathname();
            $copia = $destino.DIRECTORY_SEPARATOR.$relativo;
            if (is_file($copia) && filesize($copia) === $archivo->getSize() && filemtime($copia) >= $archivo->getMTime()) {
                continue;
            }
            File::ensureDirectoryExists(dirname($copia));
            File::copy($archivo->getPathname(), $copia);
            touch($copia, $archivo->getMTime());
            $copiados++;
        }
        return [$copiados, $total];
    }

    private function dentroDe(string $ruta, string $base): bool
    {
        $ruta = str_replace('\\', '/', strtolower(realpath($ruta) ?: $ruta));
        $base = str_replace('\\', '/', strtolower(realpath($base) ?: $base));
        return $ruta === $base || str_starts_with($ruta, $base.'/');
    }
}
