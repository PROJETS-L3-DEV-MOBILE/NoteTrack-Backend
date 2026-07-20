<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['label'])]
class Semester extends Model
{
    public function subjects(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function notes(): HasManyThrough
    {
        return $this->hasManyThrough(Note::class, Subject::class);
    }
}
