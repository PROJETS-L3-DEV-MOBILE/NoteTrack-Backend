<?php

namespace Tests\Feature\Admin;

use App\Enums\NoteImportStatus;
use App\Enums\NoteStatus;
use App\Enums\NoteType;
use App\Jobs\ProcessNoteImportJob;
use App\Models\Admin;
use App\Models\Classe;
use App\Models\Note;
use App\Models\NoteImport;
use App\Models\Promotion;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\UE;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Couvre POST /admin/students/notes-import (dispatch en arrière-plan, ne
 * bloque jamais la requête HTTP) et GET /admin/students/notes-import/{id}
 * (polling de progression), ainsi que le traitement effectif du CSV par
 * NoteImportService lorsque le job est exécuté.
 */
class NoteImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $user = User::create([
            'email'    => 'admin.import.test@notetrack.test',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        return Admin::create([
            'id'      => (string) Str::uuid(),
            'username' => 'admin_import',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Prépare une classe / UE / matière / étudiant complets pour pouvoir
     * importer une note dessus.
     */
    private function makeContext(Admin $admin): array
    {
        $schoolYear = SchoolYear::create(['label' => '2025-2026']);
        $promotion = Promotion::create(['label' => 'Promo A', 'school_year_id' => $schoolYear->id]);
        $semester = Semester::create(['label' => 'S1']);

        $classe = Classe::create([
            'id'            => (string) Str::uuid(),
            'label'         => 'L1 Info',
            'total_credits' => 60,
        ]);

        $ue = UE::create([
            'id'         => (string) Str::uuid(),
            'code'       => 'UE-INFO-1',
            'label'      => 'Fondamentaux Info',
            'color'      => 'blue',
            'classe_id'  => $classe->id,
            'admin_id'   => $admin->id,
        ]);

        $teacherUser = User::create([
            'email'    => 'teacher.import.test@notetrack.test',
            'password' => Hash::make('password'),
            'role'     => 'teacher',
        ]);

        $teacher = Teacher::create([
            'id'        => (string) Str::uuid(),
            'first_name' => 'Jean',
            'last_name'  => 'Dupont',
            'user_id'   => $teacherUser->id,
            'admin_id'  => $admin->id,
        ]);

        $subject = Subject::create([
            'id'          => (string) Str::uuid(),
            'name'        => 'Algorithmique',
            'is_available' => true,
            'threshold'   => 10,
            'credits'     => 5,
            'coefficient' => 3,
            'ue_id'       => $ue->id,
            'semester_id' => $semester->id,
            'teacher_id'  => $teacher->id,
            'admin_id'    => $admin->id,
        ]);

        $studentUser = User::create([
            'email'    => 'student.import.test@notetrack.test',
            'password' => Hash::make('password'),
            'role'     => 'student',
        ]);

        $student = Student::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Marie',
            'last_name'  => 'Rakoto',
            'matricule'  => 'ET-2026-001',
            'number'     => 1,
            'classe_id'  => $classe->id,
            'user_id'    => $studentUser->id,
            'admin_id'   => $admin->id,
            'prom_id'    => $promotion->id,
        ]);

        return compact('schoolYear', 'promotion', 'semester', 'classe', 'ue', 'subject', 'student');
    }

    public function test_import_requires_admin_authentication(): void
    {
        $file = UploadedFile::fake()->create('notes.csv', 10, 'text/csv');

        $response = $this->postJson('/api/admin/students/notes-import', ['file' => $file]);

        $response->assertStatus(401);
    }

    public function test_upload_dispatches_background_job_and_responds_immediately(): void
    {
        Queue::fake();

        $admin = $this->makeAdmin();
        $ctx = $this->makeContext($admin);
        Sanctum::actingAs($admin->user, ['*']);

        $csv = "matricule,subject_id,type,value\n{$ctx['student']->matricule},{$ctx['subject']->id},TEST,15.5\n";
        $file = UploadedFile::fake()->createWithContent('notes.csv', $csv);

        $response = $this->postJson('/api/admin/students/notes-import', ['file' => $file]);

        $response->assertStatus(202);
        $response->assertJsonPath('import.status', NoteImportStatus::Pending->value);

        Queue::assertPushed(ProcessNoteImportJob::class);

        // La note ne doit PAS avoir été créée de façon synchrone dans la
        // requête HTTP : c'est tout l'enjeu du traitement en background.
        $this->assertDatabaseCount('notes', 0);
        $this->assertDatabaseCount('note_imports', 1);
    }

    public function test_processing_the_job_imports_valid_rows_and_reports_errors(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->makeContext($admin);
        Sanctum::actingAs($admin->user, ['*']);

        $csv = implode("\n", [
            'matricule,subject_id,type,value',
            "{$ctx['student']->matricule},{$ctx['subject']->id},TEST,15.5",
            "{$ctx['student']->matricule},{$ctx['subject']->id},EXAM,12",
            "UNKNOWN-MATRICULE,{$ctx['subject']->id},TEST,10", // ligne en erreur : étudiant inexistant
        ]) . "\n";

        $file = UploadedFile::fake()->createWithContent('notes.csv', $csv);

        $response = $this->postJson('/api/admin/students/notes-import', ['file' => $file]);
        $importId = $response->json('import.id');

        // On exécute le job comme le ferait `php artisan queue:work`.
        (new ProcessNoteImportJob($importId))->handle(app(\App\Services\NoteImportService::class));

        $import = NoteImport::findOrFail($importId);

        $this->assertSame(NoteImportStatus::CompletedWithErrors, $import->status);
        $this->assertSame(2, $import->imported_count);
        $this->assertSame(1, $import->failed_count);
        $this->assertNotEmpty($import->errors);

        $this->assertDatabaseHas('notes', [
            'student_id' => $ctx['student']->id,
            'subject_id' => $ctx['subject']->id,
            'type'       => NoteType::Test->value,
            'status'     => NoteStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('notes', [
            'student_id' => $ctx['student']->id,
            'subject_id' => $ctx['subject']->id,
            'type'       => NoteType::Exam->value,
        ]);
    }

    public function test_reimporting_an_existing_note_updates_it_and_records_history(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->makeContext($admin);
        Sanctum::actingAs($admin->user, ['*']);

        Note::create([
            'id'             => (string) Str::uuid(),
            'value'          => 8,
            'status'         => NoteStatus::Pending,
            'type'           => NoteType::Test,
            'student_id'     => $ctx['student']->id,
            'subject_id'     => $ctx['subject']->id,
            'created_by'     => $admin->user_id,
            'school_year_id' => $ctx['schoolYear']->id,
        ]);

        $csv = "matricule,subject_id,type,value\n{$ctx['student']->matricule},{$ctx['subject']->id},TEST,17\n";
        $file = UploadedFile::fake()->createWithContent('notes.csv', $csv);

        $response = $this->postJson('/api/admin/students/notes-import', ['file' => $file]);
        $importId = $response->json('import.id');

        (new ProcessNoteImportJob($importId))->handle(app(\App\Services\NoteImportService::class));

        $import = NoteImport::findOrFail($importId);
        $this->assertSame(NoteImportStatus::Completed, $import->status);
        $this->assertSame(1, $import->updated_count);

        $this->assertDatabaseHas('notes', [
            'student_id' => $ctx['student']->id,
            'subject_id' => $ctx['subject']->id,
            'type'       => NoteType::Test->value,
            'value'      => 17,
        ]);

        $this->assertDatabaseCount('note_histories', 1);
    }

    public function test_status_endpoint_returns_progress(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin->user, ['*']);

        $import = NoteImport::create([
            'id'                => (string) Str::uuid(),
            'original_filename' => 'notes.csv',
            'file_path'         => 'imports/notes/fake.csv',
            'status'            => NoteImportStatus::Completed,
            'total_rows'        => 10,
            'processed_rows'    => 10,
            'imported_count'    => 9,
            'failed_count'      => 1,
            'created_by'        => $admin->user_id,
        ]);

        $response = $this->getJson("/api/admin/students/notes-import/{$import->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', NoteImportStatus::Completed->value)
            ->assertJsonPath('progress_percent', 100)
            ->assertJsonPath('imported_count', 9);
    }
}
