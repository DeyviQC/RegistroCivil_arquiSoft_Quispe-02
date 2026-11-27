<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acta_defunciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acta_id')->constrained('actas')->cascadeOnDelete();

            $table->string('prenombres', 100);
            $table->string('apellido1', 80);
            $table->string('apellido2', 80);
            $table->string('dni', 20)->nullable();
            $table->string('edad', 20)->nullable(); // texto libre: "87 años", "3 meses"
            $table->string('nacionalidad', 60)->nullable()->default('PERUANA');
            $table->string('lugar_nacimiento', 200)->nullable();

            $table->date('fecha_defuncion');
            $table->time('hora_defuncion')->nullable();
            $table->string('localidad', 200);
            $table->string('lugar_ocurrencia', 200);
            $table->string('domicilio', 200)->nullable();

            $table->string('padre_nombre', 150)->nullable();
            $table->string('madre_nombre', 150)->nullable();
            $table->string('causa_defuncion', 200);

            $table->string('declarante_nombre', 150)->nullable();
            $table->string('declarante_vinculo', 60)->nullable();
            $table->string('declarante_dni', 20)->nullable();

            $table->timestamps();

            $table->index('dni');
            $table->index(['apellido1', 'apellido2']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acta_defunciones');
    }
};