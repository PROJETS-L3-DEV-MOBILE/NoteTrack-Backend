<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['semester_number'])]
class Semester extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
