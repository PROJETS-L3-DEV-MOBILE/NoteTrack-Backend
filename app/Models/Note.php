<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\NoteStatus;
use App\Enums\NoteType;

#[Fillable(['value', 'status', 'published_at', 'student_id', 'subject_id', 'created_by', 'type', 'school_year_id'])]
class Note extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'status' => NoteStatus::class,
        'type' => NoteType::class,
        'published_at' => 'datetime',
        'value' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(NoteHistory::class);
    }

    public function effectiveValue(): ?float
    {
        if ($this->status === NoteStatus::Pending) {
            return null;
        }

        if ($this->value === -1) {
            return 0;
        }

        return (float) $this->value;
    }

    // Fix #6 — RG03 : la matière est validée si la note effective atteint
    // le seuil défini sur Subject (10/20 par défaut, personnalisable par matière).
    public function isValidated(): bool
    {
        $value = $this->effectiveValue();

        if ($value === null) {
            return false;
        }

        return $value >= (float) $this->subject->threshold;
    }
}
