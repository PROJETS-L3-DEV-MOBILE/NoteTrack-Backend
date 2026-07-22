<?php

namespace App\Http\Resources\Admin\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecentSubjectResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'subject_name'         => $this->name,
            'credits'              => $this->credits,
            'coefficient'          => $this->coefficient,
            'teacher_display_name' => $this->teacher->display_name,
            'teacher_image' => $this->teacher?->image,
            'added_by'      => $this->admin?->username,
            'added_by_image' => $this->admin?->image,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}