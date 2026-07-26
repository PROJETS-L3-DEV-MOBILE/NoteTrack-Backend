<?php

namespace App\Http\Resources\Admin\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'original_filename' => $this->original_filename,
            'status'           => $this->status->value,
            'status_label'     => $this->status->label(),
            'progress_percent' => $this->progressPercent(),
            'total_rows'       => $this->total_rows,
            'processed_rows'   => $this->processed_rows,
            'imported_count'   => $this->imported_count,
            'updated_count'    => $this->updated_count,
            'failed_count'     => $this->failed_count,
            // On plafonne l'affichage des erreurs pour ne pas alourdir la
            // réponse ; NoteImportService plafonne déjà leur stockage.
            'errors'           => $this->errors,
            'school_year'      => $this->whenLoaded('schoolYear', fn () => $this->schoolYear?->label),
            'created_by'       => $this->whenLoaded('createdBy', fn () => $this->createdBy?->email),
            'started_at'       => $this->started_at?->toISOString(),
            'finished_at'      => $this->finished_at?->toISOString(),
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
