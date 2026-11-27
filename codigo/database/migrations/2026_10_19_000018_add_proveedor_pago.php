<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('pagos_tramite', fn (Blueprint $table) => $table->string('proveedor', 20)->default('TAYPI')); }
    public function down(): void { Schema::table('pagos_tramite', fn (Blueprint $table) => $table->dropColumn('proveedor')); }
};
