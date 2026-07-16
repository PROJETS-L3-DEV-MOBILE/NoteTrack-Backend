<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'total_credits', 'description'])]
class Classes extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'classes';
    protected $keyType = 'string';
    public $incrementing = false;

    // Une classe a plusieurs promotions
    public function promotions()
    {
        return $this->hasMany(PromClass::class, 'class_id');
    }
}