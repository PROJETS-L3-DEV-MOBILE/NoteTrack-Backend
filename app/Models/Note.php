<?php

namespace App\Models;

use App\Enums\NoteStatus;
use App\Enums\SessionStatus;
use App\Observers\NoteObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['value', 'status', 'is_published', 'published_at', 'student_id', 'subject_id', 'session_id', 'created_by'])]
#[ObservedBy(NoteObserver::class)]
class Note extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'status'       => NoteStatus::class,
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'value'        => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'session_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(NoteHistory::class);
    }

    // RG10 — valeur effective pour le calcul
    public function effectiveValue(): ?float
    {
        return match ($this->status) {
            NoteStatus::AbsInjustifiee => 0,     // comptée comme 0
            NoteStatus::AbsJustifiee   => null,  // non comptée
            NoteStatus::Presente       => (float) $this->value,
        };
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

    // Ajout — statut d'affichage utilisé par le dashboard admin (NotesTable),
    // distinct de `status` (présence à l'examen) : reflète où en est la note
    // dans le cycle de publication. LOCKED prime sur PUBLISHED/PENDING dès que
    // la session est verrouillée (RG08), même si la note avait déjà été publiée.
    public function publicationStatus(): string
    {
        $session = $this->relationLoaded('session') ? $this->session : $this->session()->first();

        return match (true) {
            $session?->status === SessionStatus::Verrouillee => 'LOCKED',
            $this->is_published                              => 'PUBLISHED',
            default                                           => 'PENDING',
        };
    }

}
