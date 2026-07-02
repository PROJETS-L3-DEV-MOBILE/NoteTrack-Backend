<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['first_name', 'last_name', 'email', 'user_id', 'admin_id'])]
class Teacher extends Model
{
    use HasUniqueProfile, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}