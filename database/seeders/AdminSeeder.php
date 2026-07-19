<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'email'    => 'admin@univ.test',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
        ]);

        Admin::create([
            'username' => 'admin',
            'user_id'  => $user->id,
        ]);

        $user2 = User::create([
            'email'    => 'admin@geit.com',
            'password' => Hash::make('Admin123!#'),
            'role'     => 'admin',
        ]);

        Admin::create([
            'username' => 'admin2',
            'user_id'  => $user2->id,
        ]);
    }
}
