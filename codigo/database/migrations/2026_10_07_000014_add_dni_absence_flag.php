<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['acta_nacimientos','acta_defunciones'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->boolean('dni_no_consignado')->default(false));
            DB::table($tabla)->where(function ($q) {
                $q->whereNull('dni')->orWhereIn('dni',['SIN CUI','NO SE CONOCE']);
            })->update(['dni_no_consignado'=>true]);
        }
    }
    public function down(): void
    {
        foreach (['acta_nacimientos','acta_defunciones'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn('dni_no_consignado'));
        }
    }
};
