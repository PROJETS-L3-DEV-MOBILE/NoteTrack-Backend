<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['first_name', 'last_name', 'user_id', 'admin_id'])]
class Teacher extends Model
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

    // Ajout : relation inverse de Subject::teacher(), absente jusqu'ici mais
    // nécessaire pour déterminer les enseignants "actifs" du dashboard.
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    // Ajout : `image` vit sur `users` (un seul champ pour les 3 profils), pas
    // sur `teachers`. Même pattern que Student::image() ; pensez à
    // eager-loader 'user' (ex: with(['teacher.user'])) pour éviter du N+1.
    protected function image(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn () => $this->user?->image,
        );
    }
}