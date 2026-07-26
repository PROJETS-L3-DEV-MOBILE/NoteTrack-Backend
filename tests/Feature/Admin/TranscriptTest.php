<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteStatus;
use App\Enums\NoteType;
use App\Models\Admin;
use App\Models\Classe;
use App\Models\Note;
use App\Models\Promotion;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\UE;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Couvre GET /admin/students/{student}/transcript : génération synchrone
 * du relevé de notes PDF à partir des mêmes règles de calcul que
 * GradeCalculatorService (RG02/RG03/RG04/RG06).
 */
class TranscriptTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $user = User::create([
            'email'    => 'admin.transcript.test@notetrack.test',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        return Admin::create([
            'id'       => (string) Str::uuid(),
            'username' => 'admin_transcript',
            'user_id'  => $user->id,
        ]);
    }

    private function makeContext(Admin $admin): array
    {
        $schoolYear = SchoolYear::create(['label' => '2025-2026 T']);
        $promotion = Promotion::create(['label' => 'Promo B', 'school_year_id' => $schoolYear->id]);
        $semester = Semester::create(['label' => 'S1 T']);

        $classe = Classe::create([
            'id'            => (string) Str::uuid(),
            'label'         => 'L2 Info',
            'total_credits' => 60,
        ]);

        $ue = UE::create([
            'id'        => (string) Str::uuid(),
            'code'      => 'UE-TRANSCRIPT-1',
            'label'     => 'Programmation',
            'color'     => 'green',
            'classe_id' => $classe->id,
            'admin_id'  => $admin->id,
        ]);

        $teacherUser = User::create([
            'email'    => 'teacher.transcript.test@notetrack.test',
            'password' => Hash::make('password'),
            'role'     => 'teacher',
        ]);

        $teacher = Teacher::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Paul',
            'last_name'  => 'Randria',
            'user_id'    => $teacherUser->id,
            'admin_id'   => $admin->id,
        ]);

        $subject = Subject::create([
            'id'           => (string) Str::uuid(),
            'name'         => 'Structures de données',
            'is_available' => true,
            'threshold'    => 10,
            'credits'      => 6,
            'coefficient'  => 4,
            'ue_id'        => $ue->id,
            'semester_id'  => $semester->id,
            'teacher_id'   => $teacher->id,
            'admin_id'     => $admin->id,
        ]);

        $studentUser = User::create([
            'email'    => 'student.transcript.test@notetrack.test',
            'password' => Hash::make('password'),
            'role'     => 'student',
        ]);

        $student = Student::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Fanja',
            'last_name'  => 'Andria',
            'matricule'  => 'ET-2026-042',
            'number'     => 1,
            'classe_id'  => $classe->id,
            'user_id'    => $studentUser->id,
            'admin_id'   => $admin->id,
            'prom_id'    => $promotion->id,
        ]);

        Note::create([
            'id'             => (string) Str::uuid(),
            'value'          => 16,
            'status'         => NoteStatus::Published,
            'type'           => NoteType::Test,
            'student_id'     => $student->id,
            'subject_id'     => $subject->id,
            'created_by'     => $admin->user_id,
            'school_year_id' => $schoolYear->id,
        ]);

        Note::create([
            'id'             => (string) Str::uuid(),
            'value'          => 14,
            'status'         => NoteStatus::Published,
            'type'           => NoteType::Exam,
            'student_id'     => $student->id,
            'subject_id'     => $subject->id,
            'created_by'     => $admin->user_id,
            'school_year_id' => $schoolYear->id,
        ]);

        return compact('schoolYear', 'promotion', 'semester', 'classe', 'ue', 'subject', 'student');
    }

    public function test_transcript_requires_admin_authentication(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->makeContext($admin);

        $response = $this->get("/api/admin/students/{$ctx['student']->id}/transcript");

        $response->assertStatus(401);
    }

    public function test_admin_can_generate_pdf_transcript_for_a_student(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->makeContext($admin);
        Sanctum::actingAs($admin->user, ['*']);

        $response = $this->get("/api/admin/students/{$ctx['student']->id}/transcript");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');

        // dompdf produit toujours un fichier commençant par la signature PDF standard.
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_transcript_download_forces_attachment_header(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->makeContext($admin);
        Sanctum::actingAs($admin->user, ['*']);

        $response = $this->get("/api/admin/students/{$ctx['student']->id}/transcript?download=1");

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString($ctx['student']->matricule, $response->headers->get('content-disposition'));
    }

    public function test_returns_404_for_unknown_student(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin->user, ['*']);

        $response = $this->get('/api/admin/students/' . Str::uuid() . '/transcript');

        $response->assertStatus(404);
    }
}
