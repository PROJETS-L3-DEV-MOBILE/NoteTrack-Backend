<?php

namespace App\Http\Resources\Admin\Subjects;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubjectResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'credits'     => $this->credits,
            'coefficient' => $this->coefficient,
            'threshold'   => $this->threshold,
            'teacher_id'  => $this->teacher_id,
            'teacher'     => [
                'display_name' => $this->teacher
                    ? trim("{$this->teacher->first_name} {$this->teacher->last_name}")
                    : null,
            ],
            'semester_id' => $this->semester_id,
            'semester'    => [
                'id' => $this->semester?->id,
                'label' => $this->semester?->label
            ],
        ];
    }
}
