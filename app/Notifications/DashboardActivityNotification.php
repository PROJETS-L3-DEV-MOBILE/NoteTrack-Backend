<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification "base de données" alimentant /admin/dashboard/recent-activities.
 * Remplace l'ancien modèle Eloquent App\Models\Notification par le système
 * de notifications natif de Laravel (table notifications standard).
 */
class DashboardActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $description,
        protected NotificationType $type,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type->value,
        ];
    }
}
