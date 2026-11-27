<?php

namespace Database\Seeders;

use App\Models\Acta;
use App\Models\ActaDefuncion;
use App\Models\ActaMatrimonio;
use App\Models\ActaNacimiento;
use App\Models\User;
use App\Services\PdfStorageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea 10 actas de cada tipo (30 en total) con datos ficticios y un
 * PDF de prueba real y válido (mínimo pero conforme al estándar PDF)
 * almacenado en storage/app/private/actas/, para poder probar el
 * visor de PDF y la descarga sin usar datos personales reales.
 */
class ActaSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = User::all();
        if ($usuarios->isEmpty()) {
            $this->command?->warn('No hay usuarios. Ejecuta primero UserSeeder.');
            return;
        }

        $storage = app(PdfStorageService::class);

        DB::transaction(function () use ($usuarios, $storage) {
            for ($i = 1; $i <= 10; $i++) {
                $this->crearNacimiento($i, $usuarios->random(), $storage);
                $this->crearMatrimonio($i, $usuarios->random(), $storage);
                $this->crearDefuncion($i, $usuarios->random(), $storage);
            }
        });
    }

    private function pdfDemo(string $titulo): string
    {
        $titulo = addslashes($titulo);

        return "%PDF-1.4\n"
            . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/Resources<</Font<</F1 4 0 R>>>>/MediaBox[0 0 612 792]/Contents 5 0 R>>endobj\n"
            . "4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
            . "5 0 obj<</Length 90>>stream\nBT /F1 18 Tf 72 700 Td ({$titulo}) Tj ET\nBT /F1 11 Tf 72 670 Td (Documento de prueba - datos ficticios) Tj ET\nendstream endobj\n"
            . "trailer<</Size 6/Root 1 0 R>>\n%%EOF";
    }

    private function crearNacimiento(int $i, User $usuario, PdfStorageService $storage): void
    {
        $anio = 2026;
        $numero = str_pad((string) (1000 + $i), 5, '0', STR_PAD_LEFT);

        $ruta = $storage->guardarContenido(
            $this->pdfDemo("ACTA DE NACIMIENTO N° {$numero}/{$anio}"),
            Acta::TIPO_NACIMIENTO,
            $anio,
            $numero
        );

        $acta = Acta::create([
            'numero_acta' => $numero,
            'anio' => $anio,
            'tipo' => Acta::TIPO_NACIMIENTO,
            'fecha_acta' => now()->subDays($i * 3),
            'observaciones' => null,
            'usuario_id' => $usuario->id,
            'estado' => Acta::ESTADO_REGISTRADA,
            'pdf_original' => "acta_nacimiento_{$numero}_{$anio}.pdf",
            'pdf_ruta' => $ruta,
            'pdf_hash' => hash_file('sha256', storage_path('app/' . $ruta)),
            'pdf_tamano' => filesize(storage_path('app/' . $ruta)),
        ]);

        $nombresM = ['Juan', 'Carlos', 'Luis', 'Miguel', 'José'];
        $nombresF = ['María', 'Ana', 'Rosa', 'Lucía', 'Carmen'];
        $sexo = $i % 2 === 0 ? 'F' : 'M';

        ActaNacimiento::create([
            'acta_id' => $acta->id,
            'nombres' => $sexo === 'M' ? $nombresM[$i % 5] : $nombresF[$i % 5],
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'López',
            'dni' => (string) (70000000 + $i),
            'fecha_nacimiento' => now()->subYears(0)->subDays($i * 3),
            'hora_nacimiento' => '08:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'lugar_nacimiento' => 'Hospital Municipal',
            'sexo' => $sexo,
            'nombre_padre' => 'Pedro Pérez Gómez',
            'dni_padre' => (string) (60000000 + $i),
            'nombre_madre' => 'Rosa López Díaz',
            'dni_madre' => (string) (65000000 + $i),
            'observaciones' => null,
        ]);
    }

    private function crearMatrimonio(int $i, User $usuario, PdfStorageService $storage): void
    {
        $anio = 2026;
        $numero = str_pad((string) (2000 + $i), 5, '0', STR_PAD_LEFT);

        $ruta = $storage->guardarContenido(
            $this->pdfDemo("ACTA DE MATRIMONIO N° {$numero}/{$anio}"),
            Acta::TIPO_MATRIMONIO,
            $anio,
            $numero
        );

        $acta = Acta::create([
            'numero_acta' => $numero,
            'anio' => $anio,
            'tipo' => Acta::TIPO_MATRIMONIO,
            'fecha_acta' => now()->subDays($i * 5),
            'observaciones' => null,
            'usuario_id' => $usuario->id,
            'estado' => Acta::ESTADO_REGISTRADA,
            'pdf_original' => "acta_matrimonio_{$numero}_{$anio}.pdf",
            'pdf_ruta' => $ruta,
            'pdf_hash' => hash_file('sha256', storage_path('app/' . $ruta)),
            'pdf_tamano' => filesize(storage_path('app/' . $ruta)),
        ]);

        ActaMatrimonio::create([
            'acta_id' => $acta->id,
            'c1_nombres' => 'Jorge',
            'c1_apellido_paterno' => 'García',
            'c1_apellido_materno' => 'Ruiz',
            'c1_dni' => (string) (71000000 + $i),
            'c1_fecha_nacimiento' => now()->subYears(30 + $i),
            'c2_nombres' => 'María',
            'c2_apellido_paterno' => 'Torres',
            'c2_apellido_materno' => 'Vega',
            'c2_dni' => (string) (72000000 + $i),
            'c2_fecha_nacimiento' => now()->subYears(28 + $i),
            'fecha_matrimonio' => now()->subDays($i * 5),
            'lugar_matrimonio' => 'Municipalidad - Sala de Actos',
            'observaciones' => null,
        ]);
    }

    private function crearDefuncion(int $i, User $usuario, PdfStorageService $storage): void
    {
        $anio = 2026;
        $numero = str_pad((string) (3000 + $i), 5, '0', STR_PAD_LEFT);

        $ruta = $storage->guardarContenido(
            $this->pdfDemo("ACTA DE DEFUNCIÓN N° {$numero}/{$anio}"),
            Acta::TIPO_DEFUNCION,
            $anio,
            $numero
        );

        $acta = Acta::create([
            'numero_acta' => $numero,
            'anio' => $anio,
            'tipo' => Acta::TIPO_DEFUNCION,
            'fecha_acta' => now()->subDays($i * 2),
            'observaciones' => null,
            'usuario_id' => $usuario->id,
            'estado' => Acta::ESTADO_REGISTRADA,
            'pdf_original' => "acta_defuncion_{$numero}_{$anio}.pdf",
            'pdf_ruta' => $ruta,
            'pdf_hash' => hash_file('sha256', storage_path('app/' . $ruta)),
            'pdf_tamano' => filesize(storage_path('app/' . $ruta)),
        ]);

        ActaDefuncion::create([
            'acta_id' => $acta->id,
            'nombres' => 'Manuel',
            'apellido_paterno' => 'Flores',
            'apellido_materno' => 'Castro',
            'dni' => (string) (73000000 + $i),
            'fecha_nacimiento' => now()->subYears(70 + $i),
            'fecha_defuncion' => now()->subDays($i * 2),
            'lugar_defuncion' => 'Hospital Municipal',
            'causa_defuncion' => 'Paro cardiorrespiratorio',
            'medico_certifica' => 'Dr. Ramírez Soto',
            'observaciones' => null,
        ]);
    }
}
