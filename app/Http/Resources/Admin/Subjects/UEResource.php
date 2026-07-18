<?php

namespace App\Http\Resources\Admin\Subjects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UEResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'label'    => $this->label,
            'color'    => $this->color,
            'credits'  => $this->credits,
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
        ];
    }
}
