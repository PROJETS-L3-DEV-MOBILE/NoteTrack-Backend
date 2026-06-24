<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'first_name', 'last_name', 'matricule', 'number',
    'email', 'user_id', 'admin_id', 'prom_id',
])]
class Student extends Model
{
    use HasUniqueProfile;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'prom_id');
    }
}