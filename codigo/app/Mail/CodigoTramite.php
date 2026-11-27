<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class CodigoTramite extends Mailable
{
    public function __construct(public string $codigo, public string $referencia) {}
    public function build(): self
    {
        return $this->subject('Código de acceso a tu trámite de Registro Civil')->view('emails.codigo-tramite');
    }
}
