<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_available', 'threshold', 'credits', 'coefficient', 'ue_id', 'teacher_id', 'semester_id', 'admin_id'])]
class Subject extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public function ue(): BelongsTo
    {
        return $this->belongsTo(UE::class, 'ue_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    // Ajout : cf. `semester_id` sur create_subjects_table — nécessaire pour
    // exposer `subject.semester.label` (GET /admin/subjects)
    // et valider `createSubjectSchema.semester_id`.
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}