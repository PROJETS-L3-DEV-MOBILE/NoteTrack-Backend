<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name ?? "{$this->first_name} {$this->last_name}",
            'email' => $this->user?->email,
            'image' => $this->image,
            'subjects_count' => (int) ($this->subjects_count ?? 0),
            'classes_count' => (int) ($this->classes_count ?? 0),
            'added_by' => [
                'id' => $this->admin?->id,
                'username' => $this->admin?->username,
                'email' => $this->admin?->email,
            ],
        ];
    }
}
