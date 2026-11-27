<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acta_nacimientos', function (Blueprint $table) {
            foreach (['madre_nombre', 'madre_dni', 'padre_nombre', 'padre_dni'] as $campo) {
                $table->string($campo, str_ends_with($campo, '_dni') ? 8 : 200)->nullable();
                $table->string($campo.'_estado', 20)->default('PENDIENTE');
            }
            $table->unsignedInteger('filiacion_version')->default(0);
        });
        Schema::create('filiacion_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acta_nacimiento_id')->constrained('acta_nacimientos')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('antes');
            $table->json('despues');
            $table->string('pdf_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('filiacion_historial');
        Schema::table('acta_nacimientos', function (Blueprint $table) {
            foreach (['madre_nombre', 'madre_dni', 'padre_nombre', 'padre_dni'] as $campo) $table->dropColumn([$campo, $campo.'_estado']);
            $table->dropColumn('filiacion_version');
        });
    }
};
