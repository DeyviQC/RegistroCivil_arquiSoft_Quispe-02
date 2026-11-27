<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pagos_tramite', function (Blueprint $table) {
            $table->string('mercadopago_preferencia_id')->nullable()->unique();
            $table->text('mercadopago_checkout_url')->nullable();
            $table->string('mercadopago_pago_id')->nullable()->unique();
        });
    }
    public function down(): void {
        Schema::table('pagos_tramite', fn (Blueprint $table) => $table->dropColumn([
            'mercadopago_preferencia_id', 'mercadopago_checkout_url', 'mercadopago_pago_id',
        ]));
    }
};
