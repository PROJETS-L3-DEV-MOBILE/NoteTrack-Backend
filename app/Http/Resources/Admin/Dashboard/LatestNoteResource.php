<?php

namespace App\Http\Resources\Admin\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LatestNoteResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'note_id' => $this->id,
            'value'   => is_null($this->value) ? null : (float) $this->value,
            // Statut de publication (PUBLISHED|PENDING|LOCKED), pas le statut
            // de présence à l'examen — voir Note::publicationStatus().
            'status'  => $this->publicationStatus(),
            'student' => [
                'id'        => $this->student->id,
                'number'    => $this->student->number,
                'matricule' => $this->student->matricule,
                // Provient de users.image via l'accessor Student::image().
                'image'     => $this->student->image,
                'full_name' => trim("{$this->student->first_name} {$this->student->last_name}"),
            ],
            'subject_name'         => $this->subject->name,
            'teacher_display_name' => $this->subject->teacher
                ? trim("{$this->subject->teacher->first_name} {$this->subject->teacher->last_name}")
                : null,
            'teacher_image' => $this->subject->teacher?->image,
            'date' => $this->created_at?->toIso8601String(),
        ];
    }
}