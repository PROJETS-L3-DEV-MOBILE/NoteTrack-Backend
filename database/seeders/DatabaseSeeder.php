<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
// database/seeders/DatabaseSeeder.php
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        $this->call(PromotionSeeder::class);
        $this->call(DashboardSeeder::class);
    }
}
