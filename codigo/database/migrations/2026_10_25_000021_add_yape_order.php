<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pagos_tramite', function (Blueprint $table) {
            $table->string('yape_order_id')->nullable()->unique();
            $table->string('yape_intento')->nullable()->unique();
        });
    }
    public function down(): void {
        Schema::table('pagos_tramite', function (Blueprint $table) {
            $table->dropColumn(['yape_order_id', 'yape_intento']);
        });
    }
};
