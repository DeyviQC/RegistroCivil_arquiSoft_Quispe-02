<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actas', fn (Blueprint $table) => $table->unsignedBigInteger('orden_tipo')->nullable());
        Schema::create('acta_secuencias', function (Blueprint $table) {
            $table->string('tipo', 20)->primary();
            $table->unsignedBigInteger('ultimo')->default(0);
        });
        foreach (['NACIMIENTO', 'DEFUNCION', 'MATRIMONIO'] as $tipo) {
            $ultimo = 0;
            DB::table('actas')->where('tipo', $tipo)->orderBy('id')->chunkById(100, function ($rows) use (&$ultimo) {
                foreach ($rows as $row) {
                    DB::table('actas')->where('id', $row->id)->update(['orden_tipo' => ++$ultimo]);
                }
            });
            DB::table('acta_secuencias')->insert(['tipo' => $tipo, 'ultimo' => $ultimo]);
        }
        Schema::table('actas', fn (Blueprint $table) => $table->unique(['tipo', 'orden_tipo'], 'actas_tipo_orden_unique'));
    }

    public function down(): void
    {
        Schema::table('actas', function (Blueprint $table) {
            $table->dropUnique('actas_tipo_orden_unique');
            $table->dropColumn('orden_tipo');
        });
        Schema::dropIfExists('acta_secuencias');
    }
};
