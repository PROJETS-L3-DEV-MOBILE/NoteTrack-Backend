<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'total_credits', 'description', 'class_ct', 'class_cl'])]
class Classes extends Model
{
    use HasFactory;

    protected $table = 'classes';

    // Une classe a plusieurs promotions
    public function promotions()
    {
        return $this->hasMany(PromClass::class, 'class_id');
    }
}