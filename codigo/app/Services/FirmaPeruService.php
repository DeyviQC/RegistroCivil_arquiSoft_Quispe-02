<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FirmaPeruService
{
    private function credenciales(): ?array
    {
        $ruta = config('firma.peru_credenciales');
        if (! is_string($ruta) || ! is_file($ruta) || ! is_readable($ruta)) return null;
        $datos = json_decode(file_get_contents($ruta), true);
        if (! is_array($datos)) return null;
        foreach (['client_id', 'client_secret', 'token_url'] as $key) {
            if (! is_string($datos[$key] ?? null) || trim($datos[$key]) === '') return null;
        }
        $url = parse_url($datos['token_url']);
        if (($url['scheme'] ?? '') !== 'https' || ! str_ends_with(strtolower($url['host'] ?? ''), '.gob.pe') || isset($url['user']) || isset($url['pass'])) return null;
        return $datos;
    }

    public function disponible(): bool
    {
        return $this->credenciales() !== null;
    }

    public function token(): string
    {
        $credenciales = $this->credenciales();
        if (! $credenciales) throw ValidationException::withMessages(['firma_peru' => 'La firma desde la web requiere las credenciales institucionales de Firma Perú.']);
        try {
            $response = Http::withOptions(PortalHttpOptions::trusted())->asForm()->connectTimeout(5)->timeout(15)
                ->post($credenciales['token_url'], ['client_id' => $credenciales['client_id'], 'client_secret' => $credenciales['client_secret']]);
            $body = $response->json();
            $token = is_array($body) ? ($body['token'] ?? $body['access_token'] ?? null) : (is_string($body) ? $body : trim($response->body()));
            if (! $response->successful() || ! is_string($token) || strlen($token) > 16000 || ! preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $token)) throw new \RuntimeException;
            $payload = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
            if (! is_array($payload) || ! is_numeric($payload['exp'] ?? null) || $payload['exp'] <= now()->addMinute()->timestamp) throw new \RuntimeException;
            return $token;
        } catch (\Throwable $e) {
            // Do not log the HTTP exception, credentials, URLs with capabilities or JWT.
            throw ValidationException::withMessages(['firma_peru' => 'Firma Perú no pudo autorizar el inicio. Revisa la credencial y la conexión del servidor.']);
        }
    }
}
