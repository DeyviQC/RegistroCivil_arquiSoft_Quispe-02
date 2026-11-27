<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Recovers APP_KEY from a backup's app-key.enc, to restore a database dump on a new machine. */
class RecuperarClave extends Command
{
    protected $signature = 'respaldo:clave {archivo : Ruta a app-key.enc}';

    protected $description = 'Descifra la APP_KEY guardada en un respaldo (pide la RESPALDO_FRASE).';

    public function handle(): int
    {
        $archivo = (string) $this->argument('archivo');
        if (!is_file($archivo)) {
            $this->error('No existe el archivo indicado.');
            return self::FAILURE;
        }
        $clave = CrearRespaldo::descifrarClave((string) file_get_contents($archivo), (string) $this->secret('Frase del respaldo (RESPALDO_FRASE)'));
        if (!$clave) {
            $this->error('Frase incorrecta o archivo dañado.');
            return self::FAILURE;
        }
        $this->line('Copia esta línea en el .env del equipo nuevo:');
        $this->line('APP_KEY='.$clave);
        return self::SUCCESS;
    }
}
