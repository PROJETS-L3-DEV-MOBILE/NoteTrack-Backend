<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'first_name', 'last_name', 'matricule', 'number',
    'email', 'user_id', 'admin_id', 'prom_id',
])]
class Student extends Model
{
    use HasUniqueProfile, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'prom_id');
    }

    // Fix #4 : relation manquante, pourtant utilisée par GradeCalculatorService
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
