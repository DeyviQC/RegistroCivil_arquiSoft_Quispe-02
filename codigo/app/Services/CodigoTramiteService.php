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
        $codigo = (string) random_int(100000, 999999);
        DB::transaction(function () use ($tramite, $codigo) {
            $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
            if ($actual->otp_enviado_at?->gt(now()->subMinute())) {
                throw ValidationException::withMessages(['codigo' => 'Espera un minuto antes de solicitar otro código.']);
            }
            $actual->update(['otp_hash' => Hash::make($codigo), 'otp_expira_at' => now()->addMinutes(10), 'otp_enviado_at' => now(), 'otp_intentos' => 0]);
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
