<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\SessionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['label', 'year', 'status', 'type', 'admin_id'])]
class ExamSession extends Model
{
    use HasUuids;

    // Fix #1 : renommée "exam_sessions" pour ne pas entrer en collision avec
    // la table technique "sessions" du driver de session Laravel.
    protected $table = 'exam_sessions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'status' => SessionStatus::class,
        'type'   => SessionType::class,
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function ues(): BelongsToMany
    {
        return $this->belongsToMany(UE::class, 'ue_sessions', 'session_id', 'ue_id')
            ->withPivot(['start_date', 'end_date'])
            ->using(UeSession::class)
            ->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'session_id');
    }

    // RG08 — helpers de transition de statut
    public function publish(): void
    {
        $this->update(['status' => SessionStatus::Publiee]);

        // RG04 : un étudiant ne voit ses notes qu'après publication officielle.
        // Sans cette cascade, is_published resterait à false sur chaque note
        // et les étudiants ne verraient jamais rien, même après publish().
        $this->notes()->update([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function lock(): void
    {
        $this->update(['status' => SessionStatus::Verrouillee]);
    }
}
