<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherSubjectsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'classe_id'      => (string) $this->resource['classe_id'],
            'classe_label'   => (string) $this->resource['classe_label'],
            'students_count' => (int) $this->resource['students_count'],
            'subjects_count' => (int) $this->resource['subjects_count'],
            'subjects'       => collect($this->resource['subjects'])->map(function ($subject) {
                return [
                    'id'                    => (string) $subject['id'],
                    'name'                  => (string) $subject['name'],
                    'ue'                    => (string) $subject['ue'],
                    'semester'              => [
                        'semester_number' => (int) $subject['semester_number'],
                    ],
                    'credits'               => (float) $subject['credits'],
                    'coefficient'           => (float) $subject['coefficient'],
                    'threshold'             => (float) $subject['threshold'],
                    'published_notes_count' => (int) $subject['published_notes_count'],
                    'total_notes_count'     => (int) $subject['total_notes_count'],
                ];
            })->toArray()
        ];
    }
}
