<?php
namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class RestablecerContrasena extends ResetPassword
{
    public function toMail($notifiable)
    {
        return (new MailMessage)->subject('Restablecer contraseña — Registro Civil')
            ->greeting('Hola, '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de su cuenta.')
            ->action('Restablecer contraseña', route('password.reset', ['token'=>$this->token,'email'=>$notifiable->getEmailForPasswordReset()]))
            ->line('Este enlace vence en '.config('auth.passwords.users.expire',60).' minutos y se puede utilizar una sola vez.')
            ->line('Si usted no hizo esta solicitud, no necesita realizar ninguna acción.')
            ->salutation('Registro Civil Municipal');
    }
}
