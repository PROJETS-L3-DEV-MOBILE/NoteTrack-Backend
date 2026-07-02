<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'rang'])]
class Semestre extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public function ues(): HasMany
    {
        return $this->hasMany(UE::class, 'semestre_id');
    }
}
