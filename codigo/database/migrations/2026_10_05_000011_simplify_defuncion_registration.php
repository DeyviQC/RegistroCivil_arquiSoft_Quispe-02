<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('acta_defunciones', 'apellido_nombre')) {
            Schema::table('acta_defunciones', fn (Blueprint $table) => $table->string('apellido_nombre', 200)->nullable());
        }
        Schema::table('acta_defunciones', function (Blueprint $table) {
            $table->string('prenombres', 100)->nullable()->change();
            $table->string('apellido1', 80)->nullable()->change();
            $table->string('apellido2', 80)->nullable()->change();
            $table->string('localidad', 200)->nullable()->change();
            $table->string('lugar_ocurrencia', 200)->nullable()->change();
        });
        DB::table('acta_defunciones')->whereNull('apellido_nombre')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('acta_defunciones')->where('id', $row->id)->update([
                    'apellido_nombre' => trim(($row->prenombres ?? '').' '.($row->apellido1 ?? '').' '.($row->apellido2 ?? '')),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Preserve names and previously stored details.
    }
};
