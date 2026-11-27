<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acta_nacimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acta_id')->constrained('actas')->cascadeOnDelete();

            // ---- Registro simple (lo que ya ten\u00edan) ----
            $table->string('apellido_nombre', 200);
            $table->string('dni', 15)->nullable();
            $table->date('fecha_nacimiento');
            $table->text('observaciones')->nullable();

            // ---- Datos adicionales OPCIONALES (solo si van a generar el documento) ----
            $table->enum('sexo', ['M', 'F'])->nullable();
            $table->time('hora_nacimiento')->nullable();
            $table->string('localidad', 200)->nullable();
            $table->string('lugar_ocurrencia', 200)->nullable();

            $table->string('padre_prenombres', 100)->nullable();
            $table->string('padre_apellido1', 80)->nullable();
            $table->string('padre_apellido2', 80)->nullable();
            $table->string('padre_nacionalidad', 60)->nullable();
            $table->string('padre_dni', 20)->nullable();

            $table->string('madre_prenombres', 100)->nullable();
            $table->string('madre_apellido1', 80)->nullable();
            $table->string('madre_apellido2', 80)->nullable();
            $table->string('madre_nacionalidad', 60)->nullable();
            $table->string('madre_dni', 20)->nullable();
            $table->string('madre_domicilio', 200)->nullable();

            $table->string('declarante1_nombre', 150)->nullable();
            $table->string('declarante1_vinculo', 60)->nullable();
            $table->string('declarante1_dni', 20)->nullable();
            $table->string('declarante2_nombre', 150)->nullable();
            $table->string('declarante2_vinculo', 60)->nullable();
            $table->string('declarante2_dni', 20)->nullable();

            $table->timestamps();

            $table->index('dni');
            $table->index('apellido_nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acta_nacimientos');
    }
};