<?php

namespace App\Http\Resources\Admin\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    // Le cahier des charges attend un tableau brut, sans enveloppe "data".
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'type'        => $this->type->value,
            'is_read'     => $this->is_read,
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
