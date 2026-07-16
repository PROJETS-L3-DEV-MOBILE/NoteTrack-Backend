<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['username', 'email', 'user_id'])]
class Admin extends Model
{
    use HasUniqueProfile, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    // Ajout : `image` vit sur `users` (un seul champ pour les 3 profils), pas
    // sur `admins`. Même pattern que Student::image() ; pensez à eager-loader
    // 'user' (ex: with(['admin.user'])) pour éviter du N+1.
    protected function image(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn () => $this->user?->image,
        );
    }
}