<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore(['slug' => 'ciudadano', 'nombre' => 'Ciudadano', 'descripcion' => 'Presenta y consulta sus propias solicitudes.', 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->uuid('codigo')->unique();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->string('titular', 200);
            $table->string('dni_titular', 8)->nullable();
            $table->date('fecha_acta')->nullable();
            $table->text('detalle');
            $table->string('estado', 20)->default('PRESENTADA');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['usuario_id', 'created_at']);
            $table->index(['estado', 'created_at']);
        });
        Schema::create('solicitud_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('estado', 20);
            $table->text('mensaje');
            $table->json('datos')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_eventos');
        Schema::dropIfExists('solicitudes');
        // Preserve the citizen role: existing accounts may still use it.
    }
};
