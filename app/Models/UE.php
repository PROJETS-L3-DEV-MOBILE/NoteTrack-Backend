<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'color', 'class_id', 'admin_id'])]
class UE extends Model
{
    use HasUuids;


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

    // Ajout : lien vers le niveau (classe) auquel appartient l'UE — nécessaire
    // pour le regroupement par niveau de GET /admin/subjects. Cf. `class_id`
    // sur create_ues_table.
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'class_id');
    }

    // Ajout : "credits" (doc: somme des crédits des matières de l'UE) est un
    // champ dérivé, calculé à la volée plutôt que stocké en base (il n'y a
    // pas de colonne `credits` sur `ues`). Utilise la collection déjà
    // chargée si 'subjects' a été eager-loadée (évite une requête N+1 dans
    // les listings).
    protected function credits(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn () => $this->relationLoaded('subjects')
                ? $this->subjects->sum('credits')
                : $this->subjects()->sum('credits'),
        );
    }

}