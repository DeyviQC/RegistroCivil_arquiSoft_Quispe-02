<?php

namespace App\Services;

use App\Mail\CodigoTramite;
use App\Models\TramitePublico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CodigoTramiteService
{
    public function enviar(TramitePublico $tramite): void
    {
        $espera = fn () => ValidationException::withMessages(['codigo' => 'Espera un minuto antes de solicitar otro código.']);
        $reciente = fn (?TramitePublico $t) => (bool) $t?->otp_enviado_at?->gt(now()->subMinute());
        // Cheap check without a lock first, so repeated requests do not pay for bcrypt at all.
        if ($reciente(TramitePublico::find($tramite->id))) throw $espera();
        // bcrypt is slow by design; computing it while holding the row lock made every other request for
        // the same person wait (measured in the EQ-01/EQ-03 load test). The lock now only covers the check and the write.
        $codigo = (string) random_int(100000, 999999);
        $hash = Hash::make($codigo);
        DB::transaction(function () use ($tramite, $hash, $espera, $reciente) {
            $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
            if ($reciente($actual)) throw $espera();
            $actual->update(['otp_hash' => $hash, 'otp_expira_at' => now()->addMinutes(10), 'otp_enviado_at' => now(), 'otp_intentos' => 0]);
        });
        Mail::to($tramite->email)->send(new CodigoTramite($codigo, $tramite->codigo));
    }

    public function verificar(TramitePublico $tramite, string $codigo): bool
    {
        return DB::transaction(function () use ($tramite, $codigo) {
            $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
            if (!$actual->otp_hash || !$actual->otp_expira_at?->isFuture() || $actual->otp_intentos >= 5) return false;
            $actual->increment('otp_intentos');
            if (!Hash::check($codigo, $actual->otp_hash)) return false;
            $actual->update(['email_verificado_at' => $actual->email_verificado_at ?? now(), 'otp_hash' => null, 'otp_expira_at' => null]);
            return true;
        });
    }
}
