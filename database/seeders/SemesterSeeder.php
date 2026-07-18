<?php

namespace Database\Seeders;

use App\Models\Semester;
use Illuminate\Database\Seeder;

/**
 * Aucun seeder ne créait de ligne dans `semesters` alors que
 * CreateSubjectRequest exige `semester_id` via `exists:semesters,id`.
 * Sans ce seeder, POST /admin/ues/{ue}/subjects échoue systématiquement
 * en validation (422) sur une base fraîchement seedée.
 */
class SemesterSeeder extends Seeder
{
    public function run(): void
    {
        Semester::firstOrCreate(['semester_number' => 1]);
        Semester::firstOrCreate(['semester_number' => 2]);
    }
}
