<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acta_matrimonios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acta_id')->constrained('actas')->cascadeOnDelete();

            // ---- Registro simple (lo que ya ten\u00edan) ----
            $table->string('apellido_nombre', 250);
            $table->string('dni', 40)->nullable();
            $table->date('fecha_matrimonio');
            $table->text('observaciones')->nullable();

            // ---- Datos adicionales OPCIONALES (solo si van a generar el documento) ----
            $table->string('lugar_celebracion', 200)->nullable();
            $table->string('expediente', 60)->nullable();

            $table->string('c1_prenombres', 100)->nullable();
            $table->string('c1_apellido1', 80)->nullable();
            $table->string('c1_apellido2', 80)->nullable();
            $table->string('c1_dni', 20)->nullable();
            $table->unsignedTinyInteger('c1_edad')->nullable();
            $table->string('c1_estado_civil', 30)->nullable();
            $table->string('c1_nacionalidad', 60)->nullable();
            $table->string('c1_lugar_nacimiento', 200)->nullable();

            $table->string('c2_prenombres', 100)->nullable();
            $table->string('c2_apellido1', 80)->nullable();
            $table->string('c2_apellido2', 80)->nullable();
            $table->string('c2_dni', 20)->nullable();
            $table->unsignedTinyInteger('c2_edad')->nullable();
            $table->string('c2_estado_civil', 30)->nullable();
            $table->string('c2_nacionalidad', 60)->nullable();
            $table->string('c2_lugar_nacimiento', 200)->nullable();

            $table->timestamps();

            $table->index('apellido_nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acta_matrimonios');
    }
};