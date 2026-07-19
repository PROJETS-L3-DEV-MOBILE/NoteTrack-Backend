<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
            'full_name' => "{$this->first_name} {$this->last_name}",
            'image' => $this->image,
            'matricule' => $this->matricule,
            'promotion' => $this->promotion?->label ?? 'N/A',
            'school_year' => $this->promotion?->prom_year ?? 'N/A',
            'status' => $this->is_active ? 'ACTIVE' : 'SUSPENDED',
            'average' => $this->average,
            'mention' => $this->mention,
            'enrolled_at' => $this->created_at->toISOString(),
        ];
    }
}
