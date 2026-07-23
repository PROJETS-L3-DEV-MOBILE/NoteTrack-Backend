<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NewAccountCredentialsNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountCreationService
{
    public function createUserWithCredentials(string $email, string $role): User
    {
        $plainPassword = Str::password(12);

        $user = User::create([
            'email'    => $email,
            'password' => Hash::make($plainPassword),
            'role'     => $role,
        ]);

        $user->notify(new NewAccountCredentialsNotification($email, $plainPassword));

        return $user;
    }
}