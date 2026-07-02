<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'credits', 'admin_id'])]
class UE extends Model
{
    use HasUuids;

    // Fix : sans ce $table explicite, Laravel déduit 'u_e_s' du nom de classe
    // "UE" (Str::plural + Str::snake sur un nom tout en majuscules), au lieu
    // de la vraie table 'ues'. Bug confirmé par le smoke test (QueryException
    // "Table 'u_e_s' doesn't exist").
    protected $table = 'ues';
    protected $keyType = 'string';
    public $incrementing = false;

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'ue_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(ExamSession::class, 'ue_sessions', 'ue_id', 'session_id')
            ->withPivot(['start_date', 'end_date'])
            ->using(UeSession::class)
            ->withTimestamps();
    }
}