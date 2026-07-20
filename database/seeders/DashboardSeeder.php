<?php

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Enums\NoteType;
use App\Enums\NoteStatus;
use App\Models\Admin;
use App\Models\Classe;
use App\Models\Note;
use App\Models\Promotion;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\UE;
use App\Models\User;
use App\Notifications\DashboardActivityNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Jeu de données de démonstration pour vérifier les 5 endpoints de
 * `App\Http\Controllers\Admin\DashboardController` :
 * /stats, /results, /recent-activities, /latest-notes, /recent-subjects.
 *
 * Lancer avec : php artisan db:seed --class=DashboardSeeder
 */
class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $admin = $this->seedAdmin();
            $teachers = $this->seedTeachers($admin, 3);

            $classe = $this->seedClasse();

            [$ue, $subjects] = $this->seedAcademicStructure($admin, $teachers, $classe);

            [$promotion, $students] = $this->seedStudents($admin, $classe, 3);

            $this->seedNotes($admin, $subjects, $students);

            $this->seedNotifications($admin, $subjects, $students);
        });

        $this->command?->info('DashboardSeeder : jeu de données créé avec succès.');
    }

    private function seedAdmin(): Admin
    {
        $user = User::create([
            'email' => 'admin.demo@tutoconnect.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        return Admin::create([
            'username' => 'admin.demo',
            'user_id' => $user->id,
        ]);
    }

    /**
     * @return array<int, Teacher>
     */
    private function seedTeachers(Admin $admin, int $count): array
    {
        $names = [
            ['Hasina', 'Rakotomalala'],
            ['Fanja', 'Andrianina'],
            ['Tiana', 'Rasoanaivo'],
        ];

        return collect($names)->take($count)->map(function (array $name) use ($admin) {
            [$first, $last] = $name;

            $user = User::create([
                'email' => Str::slug($first . '.' . $last) . '@tutoconnect.test',
                'password' => Hash::make('password'),
                'role' => 'teacher',
            ]);

            return Teacher::create([
                'first_name' => $first,
                'last_name' => $last,
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'display_name' => "$first $last"
            ]);
        })->all();
    }

    private function seedClasse(): Classe
    {
        return Classe::create([
            'label' => 'L3-Dev Web et Mobile',
            'total_credits' => 60,
            'description' => 'Licence 3, option Développement Web et Mobile',
        ]);
    }

    /**
     * @param  array<int, Teacher>  $teachers
     * @return array{0: UE, 1: array<int, Subject>}
     */
    private function seedAcademicStructure(Admin $admin, array $teachers, Classe $classe): array
    {
        $ue = UE::create([
            'code' => 'UE-INFO-501',
            'label' => 'Génie Logiciel',
            'color' => '#4F46E5',
            'class_id' => $classe->id,
            'admin_id' => $admin->id,
        ]);

        $semester = Semester::firstOrCreate(['label' => 'Semestre 1']);

        $subjectsData = [
            ['name' => 'Algorithmique', 'credits' => 3, 'coefficient' => 2, 'teacher' => $teachers[0]],
            ['name' => 'Bases de Données', 'credits' => 2, 'coefficient' => 1, 'teacher' => $teachers[1] ?? $teachers[0]],
        ];

        $subjects = collect($subjectsData)->map(fn(array $data) => Subject::create([
            'name' => $data['name'],
            'is_available' => true,
            'threshold' => 10,
            'credits' => $data['credits'],
            'coefficient' => $data['coefficient'],
            'ue_id' => $ue->id,
            'semester_id' => $semester->id,
            'teacher_id' => $data['teacher']->id,
            'admin_id' => $admin->id,
        ]))->all();

        return [$ue, $subjects];
    }

    /**
     * @return array{0: Promotion, 1: array<int, Student>}
     */
    private function seedStudents(Admin $admin, Classe $classe, int $count): array
    {
        $promotion = Promotion::create([
            'label' => 'L3 Informatique',
            'prom_year' => 2026,
            'school_year_id' => 1
        ]);

        $students = collect(range(1, $count))->map(function (int $number) use ($admin, $promotion, $classe) {
            $user = User::create([
                'email' => "etudiant{$number}@tutoconnect.test",
                'password' => Hash::make('password'),
                'role' => 'student',
            ]);

            return Student::create([
                'first_name' => "Étudiant{$number}",
                'last_name' => 'Démo',
                'matricule' => sprintf('ETU-2026-%03d', $number),
                'number' => $number,
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'prom_id' => $promotion->id,
                'classe_id' => $classe->id,
            ]);
        })->all();

        return [$promotion, $students];
    }

    /**
     * Generate notes while following correct flow (Test -> Exam -> Makeup if fail)
     *
     * @param  array<int, Subject>  $subjects
     * @param  array<int, Student>  $students
     */
    private function seedNotes(Admin $admin, array $subjects, array $students): void
    {
        $mockValues = [14.5, 12.0, 8.5, 16.0, 10.0, -1, 5.0, 7.5];
        $valueIndex = 0;

        foreach ($students as $student) {
            foreach ($subjects as $subject) {
                $testValue = $mockValues[$valueIndex % count($mockValues)];
                $valueIndex++;

                Note::create([
                    'id'         => Str::uuid(),
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'type'       => NoteType::Test,
                    'value'      => $testValue,
                    'status'     => NoteStatus::Pending,
                    'created_by' => $admin->user_id,
                    'school_year_id' => 1,
                ]);

                $examValue = $mockValues[$valueIndex % count($mockValues)];
                $valueIndex++;

                Note::create([
                    'id'         => Str::uuid(),
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'type'       => NoteType::Exam,
                    'value'      => $examValue,
                    'status'     => NoteStatus::Pending,
                    'school_year_id' => 1,
                    'created_by' => $admin->user_id,
                ]);

                $realTestScore = max(0, $testValue);
                $realExamScore = max(0, $examValue);
                $totalScore = $realTestScore + $realExamScore;

                if ($totalScore <= $subject->threshold) {
                    $makeupValue = $mockValues[$valueIndex % count($mockValues)];
                    $valueIndex++;

                    Note::create([
                        'id'         => Str::uuid(),
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'type'       => NoteType::Makeup,
                        'value'      => $makeupValue,
                        'status'     => NoteStatus::Pending,
                        'created_by' => $admin->user_id,
                        'school_year_id' => 1,
                    ]);
                }
            }
        }

        // Publie toutes les notes générées ci-dessus, en cohérence avec la
        // notification "Notes publiées" créée dans seedNotifications()
        // (session normale 2025-2026, il y a 2 jours). Sans ça, published_at
        // reste null et status reste PENDING pour toutes les notes : le stat
        // card "published_notes" et le chart "/results" restent bloqués à 0,
        // car GradeCalculatorService::subjectEffectiveValue() n'exploite que
        // les notes au statut Published (RG04).
        Note::query()
            ->whereIn('student_id', collect($students)->pluck('id'))
            ->update([
                'status'       => NoteStatus::Published,
                'published_at' => now()->subDays(2),
            ]);
    }

    /**
     * @param  array<int, Subject>  $subjects
     * @param  array<int, Student>  $students
     */
    private function seedNotifications(Admin $admin, array $subjects, array $students): void
    {
        $activities = [
            [
                'title' => 'Nouvelle matière ajoutée',
                'description' => "La matière « {$subjects[0]->name} » a été ajoutée.",
                'type' => NotificationType::NewSubject,
                'is_read' => true,
                'daysAgo' => 6,
            ],
            [
                'title' => 'Nouvel étudiant inscrit',
                'description' => "L'étudiant « {$students[0]->first_name} {$students[0]->last_name} » a été inscrit.",
                'type' => NotificationType::NewStudent,
                'is_read' => true,
                'daysAgo' => 5,
            ],
            [
                'title' => 'Notes importées',
                'description' => 'Un import de notes a été effectué pour la session normale 2025-2026.',
                'type' => NotificationType::NoteImportation,
                'is_read' => false,
                'daysAgo' => 3,
            ],
            [
                'title' => 'Notes publiées',
                'description' => 'Les notes de la session normale 2025-2026 ont été publiées.',
                'type' => NotificationType::NotePublished,
                'is_read' => false,
                'daysAgo' => 2,
            ],
            [
                'title' => 'Session verrouillée',
                'description' => 'La session normale 2024-2025 a été verrouillée.',
                'type' => NotificationType::NoteLocked,
                'is_read' => false,
                'daysAgo' => 0,
            ],
        ];

        foreach ($activities as $activity) {
            $admin->notify(new DashboardActivityNotification(
                $activity['title'],
                $activity['description'],
                $activity['type'],
            ));

            // ->notify() horodate au moment de l'envoi ; on recale created_at
            // (et read_at pour les activités marquées comme lues) sur la
            // chronologie voulue pour la démo, comme le faisait l'ancien
            // ->forceFill(...)->saveQuietly().
            $sentAt = now()->subDays($activity['daysAgo']);

            $admin->notifications()->latest('created_at')->first()->forceFill([
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
                'read_at'    => $activity['is_read'] ? $sentAt : null,
            ])->saveQuietly();
        }
    }
}
