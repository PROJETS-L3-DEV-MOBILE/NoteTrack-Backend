<?php

namespace App\Http\Resources\Admin\Subjects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'level'           => $this->label,
            'ue_count'        => $this->ues_count,
            'subjects_count'  => $this->subjects_count,
            'ue'              => UEResource::collection($this->whenLoaded('ues')),
        ];
    }
}
