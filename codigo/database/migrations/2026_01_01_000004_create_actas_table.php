<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_acta', 20);
            $table->unsignedSmallInteger('anio');
            $table->enum('tipo', ['NACIMIENTO', 'MATRIMONIO', 'DEFUNCION']);
            $table->date('fecha_acta');
            $table->string('sobre', 30);
            $table->unsignedTinyInteger('correlativo');
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->constrained('users');
            $table->enum('estado', ['BORRADOR', 'REGISTRADA', 'ANULADA'])->default('REGISTRADA');

            // Nulos mientras el acta está en BORRADOR (defunción sin firmar aún)
            $table->string('pdf_original')->nullable();
            $table->string('pdf_ruta')->nullable();
            $table->char('pdf_hash', 64)->nullable();
            $table->unsignedBigInteger('pdf_tamano')->nullable();

            $table->timestamps();

            $table->unique(['numero_acta', 'anio', 'tipo'], 'actas_numero_anio_tipo_unique');
            $table->unique('pdf_hash', 'actas_pdf_hash_unique');
            $table->unique(['tipo', 'sobre', 'correlativo'], 'actas_tipo_sobre_correlativo_unique');

            $table->index('numero_acta');
            $table->index('anio');
            $table->index('tipo');
            $table->index('fecha_acta');
            $table->index('sobre');
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actas');
    }
};