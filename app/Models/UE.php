<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'label', 'credits', 'admin_id'])]
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

}