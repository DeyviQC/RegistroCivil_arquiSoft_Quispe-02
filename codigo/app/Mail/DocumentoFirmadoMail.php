<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;

class DocumentoFirmadoMail extends Mailable
{
    public function __construct(public int $solicitud, public string $ruta) {}

    public function build()
    {
        return $this->subject('Documento firmado · Solicitud N.º '.$this->solicitud)->view('emails.documento-firmado');
    }

    public function attachments(): array
    {
        return [Attachment::fromStorageDisk('local', $this->ruta)->as('solicitud-'.$this->solicitud.'-firmada.pdf')->withMime('application/pdf')];
    }
}
