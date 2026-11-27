<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramites_publicos', function (Blueprint $table) {
            $table->id();
            $table->uuid('codigo')->unique();
            $table->foreignId('acta_id')->constrained('actas')->restrictOnDelete();
            $table->text('datos');
            $table->string('email');
            $table->string('terminos_version');
            $table->timestamp('aceptado_at');
            $table->string('identidad_estado')->default('PENDIENTE_REVISION');
            $table->string('otp_hash')->nullable();
            $table->timestamp('otp_expira_at')->nullable();
            $table->timestamp('otp_enviado_at')->nullable();
            $table->unsignedTinyInteger('otp_intentos')->default(0);
            $table->timestamp('email_verificado_at')->nullable();
            $table->string('estado')->default('PENDIENTE_PAGO')->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('pagos_tramite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_publico_id')->unique()->constrained('tramites_publicos')->restrictOnDelete();
            $table->uuid('idempotencia')->unique();
            $table->uuid('proveedor_id')->nullable()->unique();
            $table->unsignedInteger('importe_centimos');
            $table->string('estado')->default('CREANDO');
            $table->longText('qr')->nullable();
            $table->timestamp('expira_at')->nullable();
            $table->timestamp('pagado_at')->nullable();
            $table->timestamps();
        });
        Schema::create('tramite_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_publico_id')->constrained('tramites_publicos')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('estado');
            $table->text('mensaje');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_eventos');
        Schema::dropIfExists('pagos_tramite');
        Schema::dropIfExists('tramites_publicos');
    }
};
