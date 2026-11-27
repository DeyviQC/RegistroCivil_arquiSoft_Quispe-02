<?php

use App\Models\TramitePublico;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Seguir mi solicitud" and the staff inbox look requests up by e-mail and DNI.
 * The DNI lives encrypted inside "datos", so a keyed hash (blind index) allows an
 * indexed exact match without storing the DNI in clear text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites_publicos', function (Blueprint $table) {
            $table->index('email');
            $table->char('dni_hash', 64)->nullable()->index()->after('email');
        });
        TramitePublico::query()->select(['id', 'datos'])->chunkById(200, function ($tramites) {
            foreach ($tramites as $tramite) {
                $hash = TramitePublico::hashDni($tramite->datos['dni'] ?? null);
                if ($hash) TramitePublico::whereKey($tramite->id)->update(['dni_hash' => $hash]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites_publicos', function (Blueprint $table) {
            $table->dropIndex(['dni_hash']);
            $table->dropColumn('dni_hash');
            $table->dropIndex(['email']);
        });
    }
};
