<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['prom_id', 'class_id', 'type', 'start_date', 'end_date'])]
class PromClass extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'prom_classes';
    protected $keyType = 'string';
    public $incrementing = false;

    public function promotion()
    {
        return $this->belongsTo(Promotion::class, 'prom_id');
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class, 'class_id');
    }
}