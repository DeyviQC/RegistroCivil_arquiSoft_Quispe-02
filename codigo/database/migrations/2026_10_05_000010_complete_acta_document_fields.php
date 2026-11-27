<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('actas', 'sobre')) {
            Schema::table('actas', fn (Blueprint $table) => $table->string('sobre', 30)->nullable());
        }
        if (! Schema::hasColumn('actas', 'correlativo')) {
            Schema::table('actas', fn (Blueprint $table) => $table->unsignedTinyInteger('correlativo')->nullable());
        }
        $fields = [
            'acta_nacimientos' => [
                'apellido_nombre' => 200, 'sexo' => 1, 'localidad' => 200, 'lugar_ocurrencia' => 200,
                'padre_prenombres' => 100, 'padre_apellido1' => 80, 'padre_apellido2' => 80,
                'padre_nacionalidad' => 60, 'padre_dni' => 20,
                'madre_prenombres' => 100, 'madre_apellido1' => 80, 'madre_apellido2' => 80,
                'madre_nacionalidad' => 60, 'madre_dni' => 20, 'madre_domicilio' => 200,
                'declarante1_nombre' => 150, 'declarante1_vinculo' => 60, 'declarante1_dni' => 20,
                'declarante2_nombre' => 150, 'declarante2_vinculo' => 60, 'declarante2_dni' => 20,
            ],
            'acta_defunciones' => [
                'prenombres' => 100, 'apellido1' => 80, 'apellido2' => 80, 'edad' => 20,
                'nacionalidad' => 60, 'lugar_nacimiento' => 200, 'localidad' => 200,
                'lugar_ocurrencia' => 200, 'domicilio' => 200,
                'padre_nombre' => 150, 'madre_nombre' => 150,
                'declarante_nombre' => 150, 'declarante_vinculo' => 60, 'declarante_dni' => 20,
            ],
        ];

        foreach ($fields as $tableName => $columns) {
            foreach ($columns as $name => $length) {
                if (! Schema::hasColumn($tableName, $name)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->string($name, $length)->nullable());
                }
            }
            $time = $tableName === 'acta_nacimientos' ? 'hora_nacimiento' : 'hora_defuncion';
            if (! Schema::hasColumn($tableName, $time)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->time($time)->nullable());
            }
        }

        // Preserve legacy data and allow new records to omit obsolete fields.
        $legacy = [
            'acta_nacimientos' => ['nombres' => 100, 'apellido_paterno' => 80, 'apellido_materno' => 80, 'lugar_nacimiento' => 150],
            'acta_defunciones' => ['nombres' => 100, 'apellido_paterno' => 80, 'apellido_materno' => 80, 'lugar_defuncion' => 150],
        ];
        foreach ($legacy as $tableName => $columns) {
            foreach ($columns as $name => $length) {
                if (Schema::hasColumn($tableName, $name)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->string($name, $length)->nullable()->change());
                }
            }
        }
        Schema::table('acta_nacimientos', fn (Blueprint $table) => $table->string('sexo', 1)->nullable()->change());

        $copies = [
            'acta_nacimientos' => ['lugar_nacimiento' => 'lugar_ocurrencia', 'nombre_padre' => 'padre_prenombres', 'dni_padre' => 'padre_dni', 'nombre_madre' => 'madre_prenombres', 'dni_madre' => 'madre_dni'],
            'acta_defunciones' => ['nombres' => 'prenombres', 'apellido_paterno' => 'apellido1', 'apellido_materno' => 'apellido2', 'lugar_defuncion' => 'lugar_ocurrencia'],
        ];
        foreach ($copies as $tableName => $columns) {
            foreach ($columns as $source => $target) {
                if (Schema::hasColumn($tableName, $source)) {
                    DB::table($tableName)->whereNull($target)->update([$target => DB::raw($source)]);
                }
            }
        }
        if (Schema::hasColumn('acta_nacimientos', 'nombres')) {
            DB::table('acta_nacimientos')->whereNull('apellido_nombre')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('acta_nacimientos')->where('id', $row->id)->update([
                        'apellido_nombre' => trim(($row->apellido_paterno ?? '').' '.($row->apellido_materno ?? '').' '.($row->nombres ?? '')),
                    ]);
                }
            });
        }

        Schema::table('actas', function (Blueprint $table) {
            $table->string('pdf_original')->nullable()->change();
            $table->string('pdf_ruta')->nullable()->change();
            $table->char('pdf_hash', 64)->nullable()->change();
            $table->unsignedBigInteger('pdf_tamano')->nullable()->change();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE actas MODIFY estado ENUM('BORRADOR', 'REGISTRADA', 'ANULADA') NOT NULL DEFAULT 'REGISTRADA'");
        }
    }

    public function down(): void
    {
        // Data-preserving migration: do not remove populated document fields.
    }
};
