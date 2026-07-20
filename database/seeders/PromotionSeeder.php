<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promotion;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Promotion::create([
            'label'    => 'PROM HAVANA',
            'prom_year' => 2025,
            'school_year_id' => 1,
        ]);
    }
}
