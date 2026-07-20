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
        for ($i = 1; $i <= 10; $i++) {
            Semester::create(['label' => "Semestre $i"]);
        }
    }
}
