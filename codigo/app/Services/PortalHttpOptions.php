<?php

namespace App\Services;

use RuntimeException;

class PortalHttpOptions
{
    public static function trusted(): array
    {
        $options = ['allow_redirects' => false, 'verify' => true];
        $certificate = config('portal.http_ca_file');
        if ($certificate) {
            if (!is_string($certificate) || !is_file($certificate) || !is_readable($certificate)) {
                throw new RuntimeException('El archivo de certificados HTTPS no está disponible.');
            }
            $options['verify'] = $certificate;
        }
        return $options;
    }
}
