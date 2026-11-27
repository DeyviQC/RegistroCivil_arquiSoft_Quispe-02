<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes found missing in the AC03 scalability test (50 000 acts):
 * the public search filters each detail table by its event date, the archive
 * page filters acts by state (incidents) and the staff inbox filters payments by state.
 */
return new class extends Migration
{
    private const INDICES = [
        'acta_nacimientos' => 'fecha_nacimiento',
        'acta_matrimonios' => 'fecha_matrimonio',
        'acta_defunciones' => 'fecha_defuncion',
        'actas' => 'estado',
        'pagos_tramite' => 'estado',
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabla => $columna) {
            Schema::table($tabla, fn (Blueprint $table) => $table->index($columna));
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabla => $columna) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropIndex([$columna]));
        }
    }
};
