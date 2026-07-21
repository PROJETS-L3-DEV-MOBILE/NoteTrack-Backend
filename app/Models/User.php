<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

#[Fillable(['email', 'password', 'image', 'role'])]
#[Hidden([
    'password',
    'admin',
    'student',
    'teacher',
    'created_at',
    'updated_at',
    'deleted_at',
    'is_deleted'
])]
class User extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    public function admin(): HasOne
    {
        return $this->hasOne(Admin::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    protected function profile(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: function () {
                $profile = match ($this->role) {
                    'admin'   => $this->admin,
                    'teacher' => $this->teacher,
                    'student' => $this->student,
                    default   => null,
                };

                if (!$profile) {
                    return null;
                }

                $profile->username = match ($this->role) {
                    'admin' => $profile->username,
                    default => trim("{$profile->first_name} {$profile->last_name}"),
                };

                return $profile;
            }
        );
    }
}
