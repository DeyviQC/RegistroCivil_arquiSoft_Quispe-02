<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Latest digitally signed copy of each act, so later requests for the same act can
 * be delivered without signing again. The original scan (pdf_ruta) is never replaced:
 * the copy is only valid while the original keeps the same hash it was signed over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actas', function (Blueprint $table) {
            $table->string('firmado_ruta')->nullable()->after('pdf_tamano');
            $table->char('firmado_sha256', 64)->nullable()->after('firmado_ruta');
            $table->char('firmado_original_hash', 64)->nullable()->after('firmado_sha256');
            $table->string('firmado_firmante')->nullable()->after('firmado_original_hash');
            $table->string('firmado_certificado_sha256', 128)->nullable()->after('firmado_firmante');
            $table->timestamp('firmado_at')->nullable()->after('firmado_certificado_sha256');
            $table->foreignId('firmado_por')->nullable()->after('firmado_at')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('firmado_tramite_id')->nullable()->after('firmado_por');
        });
    }

    public function down(): void
    {
        Schema::table('actas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('firmado_por');
            $table->dropColumn(['firmado_ruta', 'firmado_sha256', 'firmado_original_hash', 'firmado_firmante',
                'firmado_certificado_sha256', 'firmado_at', 'firmado_tramite_id']);
        });
    }
};
