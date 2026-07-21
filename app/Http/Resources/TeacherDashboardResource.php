<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'taught_subjects'        => (int) $this->resource['taught_subjects'],
            'participating_students' => (int) $this->resource['participating_students'],
            'published_notes'        => (int) $this->resource['published_notes'],
            'pending_notes'          => (int) $this->resource['pending_notes'],
        ];
    }
}
