<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;

/**
 * "Database" notification
 */
class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $description,
        protected NotificationType $type,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type->value,
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return [
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type->value,
        ];
    }
}
