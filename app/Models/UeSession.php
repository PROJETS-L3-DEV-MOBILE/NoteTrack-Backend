<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['ue_id', 'session_id', 'start_date', 'end_date'])]
class UeSession extends Pivot
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function ue(): BelongsTo
    {
        return $this->belongsTo(UE::class, 'ue_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'session_id');
    }
}
