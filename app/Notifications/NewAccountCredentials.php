<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAccountCredentials extends Notification
{
    use Queueable;

    public function __construct(
        protected string $email,
        protected string $password,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vos identifiants de connexion')
            ->greeting('Bienvenue !')
            ->line('Un compte a été créé pour vous sur la plateforme de gestion de notes.')
            ->line('Email : ' . $this->email)
            ->line('Mot de passe temporaire : ' . $this->password)
            ->line('Merci de le modifier dès votre première connexion.');
    }
}