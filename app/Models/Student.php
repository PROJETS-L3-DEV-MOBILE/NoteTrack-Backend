<?php

namespace App\Models;

use App\Enums\Mention;
use App\Models\Concerns\HasUniqueProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['first_name', 'last_name', 'matricule', 'number', 'user_id', 'admin_id', 'prom_id', 'classe_id', 'is_active'])]
class Student extends Model
{
    use HasUniqueProfile, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted()
    {
        static::deleted(function ($student) {
            $student->user()->delete();
        });

        static::restored(function ($student) {
            $student->user()->restore();
        });
    }

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

    // Ajout : `image` vit sur `users` (un seul champ pour les 3 profils), pas sur
    // `students`. Cet accessor expose $student->image tel qu'attendu par
    // LatestNoteResource, sans dupliquer la colonne sur students. Pensez à
    // eager-loader 'user' (ex: with(['student.user'])) pour éviter du N+1.
    protected function image(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn() => $this->user?->image,
        );
    }

    protected function average(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: function () {
                $avg = $this->notes()->avg('value');
                return $avg ? round($avg, 2) : 0.0;
            }
        );
    }

    protected function mention(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: function () {
                $avg = $this->average;

                return match (true) {
                    $avg < 10  => Mention::FAILED,
                    $avg < 12  => Mention::PASS,
                    $avg < 14  => Mention::SATISFACTORY,
                    $avg < 16  => Mention::GOOD,
                    default    => Mention::EXCELLENT,
                };
            }
        );
    }
}
