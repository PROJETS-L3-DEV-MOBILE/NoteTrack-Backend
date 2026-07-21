<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['label', 'total_credits', 'description'])]
class Classe extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    // Ajout : nécessaire au regroupement par niveau de GET /admin/subjects
    // (feature Subjects/UE). Ne touche à rien de la gestion des classes.
    public function ues(): HasMany
    {
        return $this->hasMany(UE::class, 'classe_id');
    }

    public function subjects(): HasManyThrough
    {
        return $this->hasManyThrough(Subject::class, UE::class, 'classe_id', 'ue_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function notes()
    {
        return $this->hasManyThrough(Note::class, Student::class);
    }

    protected function teachers(): Attribute
    {
        return Attribute::make(
            get: fn() => Teacher::whereHas('subjects.ue', fn($q) => $q->where('classe_id', $this->id))->get()
        );
    }
}
