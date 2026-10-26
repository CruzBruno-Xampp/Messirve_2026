<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token
    ) {
    }

    /**
     * La recuperación administrativa se envía por correo electrónico.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Construye el mensaje con una URL exclusiva del panel administrativo.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $nombre = trim((string) ($notifiable->nombre ?? ''));

        return (new MailMessage)
            ->subject('Restablecer contraseña administrativa - Messirve')
            ->greeting($nombre !== '' ? 'Hola, ' . $nombre . '.' : 'Hola.')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu acceso administrativo a Messirve.')
            ->action('Restablecer contraseña', $url)
            ->line(
                'Este enlace vence en ' .
                config('auth.passwords.users.expire', 60) .
                ' minutos.'
            )
            ->line('Si no solicitaste este cambio, podés ignorar este correo.');
    }
}
