<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable; 

#[Fillable(['email', 'password', 'role'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

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
            get: fn () => match ($this->role) {
                'admin'   => $this->admin,
                'teacher' => $this->teacher,
                'student' => $this->student,
            },
        );
    }
}