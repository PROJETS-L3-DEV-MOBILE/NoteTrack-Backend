<?php

namespace App\Models;

use App\Enums\NoteImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'original_filename',
    'file_path',
    'status',
    'total_rows',
    'processed_rows',
    'imported_count',
    'updated_count',
    'failed_count',
    'errors',
    'school_year_id',
    'created_by',
    'started_at',
    'finished_at',
])]
class NoteImport extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'status'         => NoteImportStatus::class,
        'errors'         => 'array',
        'started_at'     => 'datetime',
        'finished_at'    => 'datetime',
        'total_rows'     => 'integer',
        'processed_rows' => 'integer',
        'imported_count' => 'integer',
        'updated_count'  => 'integer',
        'failed_count'   => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * Progression (0-100) exposée au front pendant le polling.
     */
    public function progressPercent(): int
    {
        if (! $this->total_rows) {
            return $this->status->isFinished() ? 100 : 0;
        }

        return (int) min(100, round(($this->processed_rows / $this->total_rows) * 100));
    }
}
