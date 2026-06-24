<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'prom_year'])]
class Promotion extends Model
{
    use HasFactory;

    // Une promotion a plusieurs étudiants
    public function students()
    {
        return $this->hasMany(Student::class, 'prom_id');
    }

    // Une promotion a plusieurs classes
    public function classes()
    {
        return $this->hasMany(PromClass::class, 'prom_id');
    }
}