<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

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
    
    public function toArray(object $notifiable): array
    {
        return [
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type->value,
        ];
    }
}
