<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Classe;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\UE;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Couvre les 6 routes ajoutées :
 *   POST   /admin/ues
 *   PUT    /admin/ues/{ue}
 *   DELETE /admin/ues/{ue}
 *   POST   /admin/ues/{ue}/subjects
 *   PUT    /admin/ues/{ue}/subjects/{subject}
 *   DELETE /admin/ues/{ue}/subjects/{subject}
 * ainsi que GET /admin/subjects.
 *
 * S'appuie sur DatabaseSeeder (AdminSeeder, PromotionSeeder, SemesterSeeder,
 * DashboardSeeder) plutôt que sur des factories, pour rester cohérent avec
 * le jeu de données que l'équipe utilise déjà en local.
 */
class UEAndSubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * Authentifie la requête suivante en tant qu'admin seedé
     * (admin.demo@tutoconnect.test, créé par DashboardSeeder).
     */
    private function actingAsAdmin(): Admin
    {
        $admin = Admin::where('email', 'admin.demo@tutoconnect.test')->firstOrFail();

        Sanctum::actingAs($admin->user, ['*']);

        return $admin;
    }

    private function actingAsNonAdmin(): User
    {
        $user = User::create([
            'email'    => 'teacher.test@tutoconnect.test',
            'password' => Hash::make('password'),
            'role'     => 'teacher',
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ---------------------------------------------------------------
    // Auth / autorisation
    // ---------------------------------------------------------------

    public function test_ue_creation_requires_authentication(): void
    {
        $classe = Classe::first();

        $response = $this->postJson('/api/admin/ues', [
            'name'     => 'Informatique Fondamentale',
            'class_id' => $classe->id,
            'color'    => 'violet',
        ]);

        $response->assertStatus(401);
    }

    public function test_ue_creation_requires_admin_role(): void
    {
        $this->actingAsNonAdmin();
        $classe = Classe::first();

        $response = $this->postJson('/api/admin/ues', [
            'name'     => 'Informatique Fondamentale',
            'class_id' => $classe->id,
            'color'    => 'violet',
        ]);

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // GET /admin/subjects
    // ---------------------------------------------------------------

    public function test_it_lists_subjects_grouped_by_level(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/subjects');

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['id', 'level', 'ue_count', 'subjects_count', 'ue' => [
                '*' => ['id', 'label', 'color', 'credits', 'subjects' => [
                    '*' => [
                        'id', 'name', 'credits', 'coefficient', 'threshold',
                        'teacher_id', 'teacher' => ['display_name'],
                        'semester_id', 'semester' => ['label'],
                    ],
                ]],
            ]],
        ]);

        // La classe seedée par DashboardSeeder ("L3-Dev Web et Mobile") n'a
        // pas d'UE : elle ne doit donc PAS apparaître dans la réponse
        // (cf. SubjectService::groupedByLevel).
        $labels = collect($response->json())->pluck('level');
        $this->assertNotContains('L3-Dev Web et Mobile', $labels);
    }

    // ---------------------------------------------------------------
    // POST /admin/ues
    // ---------------------------------------------------------------

    public function test_it_creates_a_ue(): void
    {
        $this->actingAsAdmin();
        $classe = Classe::first();

        $response = $this->postJson('/api/admin/ues', [
            'name'     => 'Informatique Fondamentale',
            'class_id' => $classe->id,
            'color'    => 'violet', // non-hex volontaire : valide depuis le fix (doc = string, min:1)
        ]);

        $response->assertCreated();
        $response->assertJson([
            'label' => 'Informatique Fondamentale',
            'color' => 'violet',
        ]);

        $this->assertDatabaseHas('ues', [
            'label'    => 'Informatique Fondamentale',
            'class_id' => $classe->id,
        ]);
    }

    public function test_ue_creation_fails_without_required_fields(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/ues', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'class_id', 'color']);
    }

    public function test_ue_creation_fails_with_unknown_class_id(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/ues', [
            'name'     => 'Informatique Fondamentale',
            'class_id' => '00000000-0000-0000-0000-000000000000',
            'color'    => '#4F46E5',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['class_id']);
    }

    // ---------------------------------------------------------------
    // PUT /admin/ues/{ue}
    // ---------------------------------------------------------------

    public function test_it_updates_a_ue(): void
    {
        $this->actingAsAdmin();
        $ue = UE::firstOrFail(); // "Génie Logiciel", seedé par DashboardSeeder

        $response = $this->putJson("/api/admin/ues/{$ue->id}", [
            'name'     => 'Génie Logiciel Avancé',
            'class_id' => Classe::first()->id,
            'color'    => '#111111',
        ]);

        $response->assertOk();
        $response->assertJson(['label' => 'Génie Logiciel Avancé']);
    }

    public function test_updating_unknown_ue_returns_404(): void
    {
        $this->actingAsAdmin();

        $response = $this->putJson('/api/admin/ues/00000000-0000-0000-0000-000000000000', [
            'name'     => 'X',
            'class_id' => Classe::first()->id,
            'color'    => '#111111',
        ]);

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // DELETE /admin/ues/{ue}
    // ---------------------------------------------------------------

    public function test_deleting_a_ue_cascades_to_its_subjects(): void
    {
        $this->actingAsAdmin();
        $ue = UE::firstOrFail();
        $subjectIds = Subject::where('ue_id', $ue->id)->pluck('id');
        $this->assertNotEmpty($subjectIds, 'le seeder doit avoir créé des matières pour cette UE');

        $response = $this->deleteJson("/api/admin/ues/{$ue->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('ues', ['id' => $ue->id]);
        foreach ($subjectIds as $subjectId) {
            $this->assertDatabaseMissing('subjects', ['id' => $subjectId]);
        }
    }

    // ---------------------------------------------------------------
    // POST /admin/ues/{ue}/subjects
    // ---------------------------------------------------------------

    public function test_it_creates_a_subject_under_a_ue(): void
    {
        $this->actingAsAdmin();
        $ue = UE::firstOrFail();
        $teacher = Teacher::firstOrFail();
        $semester = Semester::firstOrFail();

        $response = $this->postJson("/api/admin/ues/{$ue->id}/subjects", [
            'name'         => 'Réseaux',
            'teacher_id'   => $teacher->id,
            'semester_id'  => $semester->id,
            'coefficient'  => 3,
            'threshold'    => 10,
            'credits'      => 4,
        ]);

        $response->assertCreated();
        $response->assertJson([
            'name'        => 'Réseaux',
            'coefficient' => 3,
            'threshold'   => 10,
            'credits'     => 4,
            'teacher_id'  => $teacher->id,
            'semester_id' => $semester->id,
        ]);

        $this->assertDatabaseHas('subjects', [
            'name'  => 'Réseaux',
            'ue_id' => $ue->id,
        ]);
    }

    public function test_subject_creation_fails_without_a_valid_semester(): void
    {
        $this->actingAsAdmin();
        $ue = UE::firstOrFail();
        $teacher = Teacher::firstOrFail();

        $response = $this->postJson("/api/admin/ues/{$ue->id}/subjects", [
            'name'        => 'Réseaux',
            'teacher_id'  => $teacher->id,
            'semester_id' => '00000000-0000-0000-0000-000000000000',
            'coefficient' => 3,
            'threshold'   => 10,
            'credits'     => 4,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['semester_id']);
    }

    public function test_subject_creation_fails_when_coefficient_out_of_range(): void
    {
        $this->actingAsAdmin();
        $ue = UE::firstOrFail();
        $teacher = Teacher::firstOrFail();
        $semester = Semester::firstOrFail();

        $response = $this->postJson("/api/admin/ues/{$ue->id}/subjects", [
            'name'        => 'Réseaux',
            'teacher_id'  => $teacher->id,
            'semester_id' => $semester->id,
            'coefficient' => 11, // max autorisé : 10
            'threshold'   => 10,
            'credits'     => 4,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['coefficient']);
    }

    // ---------------------------------------------------------------
    // PUT /admin/ues/{ue}/subjects/{subject}
    // ---------------------------------------------------------------

    public function test_it_updates_a_subject(): void
    {
        $this->actingAsAdmin();
        $ue = UE::firstOrFail();
        $subject = Subject::where('ue_id', $ue->id)->firstOrFail();

        $response = $this->putJson("/api/admin/ues/{$ue->id}/subjects/{$subject->id}", [
            'name'        => 'Algorithmique Avancée',
            'teacher_id'  => $subject->teacher_id,
            'semester_id' => Semester::firstOrFail()->id,
            'coefficient' => $subject->coefficient,
            'threshold'   => $subject->threshold,
            'credits'     => $subject->credits,
        ]);

        $response->assertOk();
        $response->assertJson(['name' => 'Algorithmique Avancée']);
    }

    /**
     * Vérifie le scoping des routes imbriquées ues.subjects : une matière
     * qui n'appartient PAS à l'UE indiquée dans l'URL doit renvoyer 404,
     * même si son id existe bel et bien en base.
     */
    public function test_updating_a_subject_through_the_wrong_ue_returns_404(): void
    {
        $this->actingAsAdmin();
        $admin = Admin::where('email', 'admin.demo@tutoconnect.test')->firstOrFail();

        $otherUe = UE::create([
            'code'     => 'UE-AUTRE-001',
            'label'    => 'Autre UE',
            'admin_id' => $admin->id,
        ]);

        $subject = Subject::firstOrFail(); // appartient à une autre UE

        $response = $this->putJson("/api/admin/ues/{$otherUe->id}/subjects/{$subject->id}", [
            'name'        => 'X',
            'teacher_id'  => $subject->teacher_id,
            'semester_id' => Semester::firstOrFail()->id,
            'coefficient' => 3,
            'threshold'   => 10,
            'credits'     => 4,
        ]);

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // DELETE /admin/ues/{ue}/subjects/{subject}
    // ---------------------------------------------------------------

    public function test_it_deletes_a_subject(): void
    {
        $this->actingAsAdmin();
        $ue = UE::firstOrFail();
        $subject = Subject::where('ue_id', $ue->id)->firstOrFail();

        $response = $this->deleteJson("/api/admin/ues/{$ue->id}/subjects/{$subject->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }
}
