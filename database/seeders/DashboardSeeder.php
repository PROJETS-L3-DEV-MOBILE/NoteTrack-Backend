<?php

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Enums\NoteStatus;
use App\Enums\SessionStatus;
use App\Models\Admin;
use App\Models\Classe;
use App\Models\ExamSession;
use App\Models\Note;
use App\Models\Notification;
use App\Models\PromClass;
use App\Models\Promotion;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\UE;
use App\Models\User;
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
 *
 * Idempotence : ce seeder crée systématiquement de nouvelles lignes (uuid,
 * emails uniques via Str::random). Le relancer plusieurs fois est sans danger
 * mais accumule les données ; sur un environnement de test, on peut faire
 * `php artisan migrate:fresh --seed` avant de le rejouer.
 */
class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $admin = $this->seedAdmin();
            $teachers = $this->seedTeachers($admin, 3);

            [$ue, $subjects] = $this->seedAcademicStructure($admin, $teachers);

            [$promotion, $students] = $this->seedStudents($admin, 12);

            [$currentSession, $pastSession] = $this->seedExamSessions($admin);

            $this->seedNotes($students, $subjects, $currentSession, $pastSession, $teachers[0]);

            // Verrouillée APRES la saisie des notes : sans ça, l'observateur
            // NoteObserver (RG08) refuserait la création des notes ci-dessus.
            $pastSession->lock();

            $this->seedNotifications($admin, $subjects, $students);
        });

        $this->command?->info('DashboardSeeder : jeu de données créé avec succès.');
    }

    private function seedAdmin(): Admin
    {
        $user = User::create([
            'email'    => 'admin.demo@tutoconnect.test',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        return Admin::create([
            'username' => 'admin.demo',
            'email'    => $user->email,
            'user_id'  => $user->id,
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
                'email'    => Str::slug($first . '.' . $last) . '@tutoconnect.test',
                'password' => Hash::make('password'),
                'role'     => 'teacher',
            ]);

            return Teacher::create([
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => $user->email,
                'user_id'    => $user->id,
                'admin_id'   => $admin->id,
            ]);
        })->all();
    }

    /**
     * @param  array<int, Teacher>  $teachers
     * @return array{0: UE, 1: array<int, Subject>}
     */
    private function seedAcademicStructure(Admin $admin, array $teachers): array
    {
        // Fix : App\Models\Semestre n'existe pas (le modèle réel s'appelle
        // Semester et n'a ni relation ues(), ni colonne semester_id sur `ues`
        // — cf. suggestions). UE::admin_id est le seul champ mass-assignable
        // pertinent ici (#[Fillable(['code', 'label', 'credits', 'admin_id'])]),
        // donc on crée l'UE directement.
        $ue = UE::create([
            'code'     => 'UE-INFO-501',
            'label'    => 'Génie Logiciel',
            'credits'  => 5,
            'admin_id' => $admin->id,
        ]);

        $subjectsData = [
            ['name' => 'Algorithmique', 'credits' => 3, 'coefficient' => 2, 'teacher' => $teachers[0]],
            ['name' => 'Bases de Données', 'credits' => 2, 'coefficient' => 1, 'teacher' => $teachers[1] ?? $teachers[0]],
        ];

        $subjects = collect($subjectsData)->map(fn(array $data) => Subject::create([
            'name'         => $data['name'],
            'is_available' => true,
            'threshold'    => 10,
            'credits'      => $data['credits'],
            'coefficient'  => $data['coefficient'],
            'ue_id'        => $ue->id,
            'teacher_id'   => $data['teacher']->id,
            'admin_id'     => $admin->id,
        ]))->all();

        return [$ue, $subjects];
    }

    /**
     * @return array{0: Promotion, 1: array<int, Student>}
     */
    private function seedStudents(Admin $admin, int $count): array
    {
        $promotion = Promotion::create([
            'label' => 'L3 Informatique',
            'prom_year' => 2026
        ]);

        $classe = Classe::create([
            'label'         => 'L3-Dev Web et Mobile',
            'total_credits' => 60,
            'description'   => 'Licence 3, option Développement Web et Mobile',
        ]);

        $students = collect(range(1, $count))->map(function (int $number) use ($admin, $promotion, $classe) {
            $user = User::create([
                'email'    => "etudiant{$number}@tutoconnect.test",
                'password' => Hash::make('password'),
                'role'     => 'student',
            ]);

            return Student::create([
                'first_name' => "Étudiant{$number}",
                'last_name'  => 'Démo',
                'matricule'  => sprintf('ETU-2026-%03d', $number),
                'number'     => $number,
                'email'      => $user->email,
                'user_id'    => $user->id,
                'admin_id'   => $admin->id,
                'prom_id'    => $promotion->id,
                'classe_id'   => $classe->id,
            ]);
        })->all();

        return [$promotion, $students];
    }

    /**
     * @return array{0: ExamSession, 1: ExamSession} [session courante, session passée]
     */
    private function seedExamSessions(Admin $admin): array
    {
        // Créée en premier : sera la session la plus ancienne, verrouillée
        // ensuite dans run(). Sert à tester le statut LOCKED et le filtre
        // school_year sur /results.
        $pastSession = ExamSession::create([
            'label'    => 'Session normale 2024-2025',
            'year'     => '2024-2025',
            'status'   => SessionStatus::Publiee,
            'admin_id' => $admin->id,
        ]);

        // Créée en second : devient la session "la plus récente" utilisée
        // par défaut par /results quand school_year n'est pas fourni.
        $currentSession = ExamSession::create([
            'label'    => 'Session normale 2025-2026',
            'year'     => '2025-2026',
            'status'   => SessionStatus::Publiee,
            'admin_id' => $admin->id,
        ]);

        // Fix : UE::sessions() n'existe pas (aucune relation many-to-many, ni
        // table pivot ue_exam_session en base) — le lien entre UE et
        // ExamSession se fait uniquement via Subject/Note (session_id),
        // cf. suggestions.
        return [$currentSession, $pastSession];
    }

    /**
     * Répartit les notes pour couvrir les 5 catégories de /results (échec à
     * très bien), un étudiant non encore évalué, des notes en attente
     * (PENDING) et des notes sur la session passée (deviendra LOCKED).
     *
     * @param  array<int, Student>  $students
     * @param  array<int, Subject>  $subjects
     */
    private function seedNotes(
        array $students,
        array $subjects,
        ExamSession $currentSession,
        ExamSession $pastSession,
        Teacher $author,
    ): void {
        [$algo, $bdd] = $subjects;

        // (valeur Algo, valeur BDD) par étudiant, choisies pour retomber
        // précisément dans chaque tranche de mention (moyenne pondérée
        // coef. 2 / coef. 1) : failed, pass, satisfactory, good, excellent.
        $gradedPairs = [
            [6, 7],
            [8, 5],       // failed   (<10)
            [10, 10],
            [11, 10],   // pass     (10-11.99)
            [14, 12],
            [12, 14],   // satisfactory (12-13.99)
            [15, 14],
            [16, 13],   // good     (14-15.99)
            [18, 16],
            [19, 18],   // excellent (>=16)
        ];

        $gradedStudents = array_slice($students, 0, count($gradedPairs));
        $ungradedStudents = array_slice($students, count($gradedPairs));

        foreach ($gradedStudents as $index => $student) {
            [$algoValue, $bddValue] = $gradedPairs[$index];

            $this->createPublishedNote($student, $algo, $currentSession, $author, $algoValue, daysAgo: 10 - $index);
            $this->createPublishedNote($student, $bdd, $currentSession, $author, $bddValue, daysAgo: 10 - $index);
        }

        // Étudiants non encore évalués sur la session courante : notes
        // saisies mais pas publiées (PENDING), pour tester /latest-notes et
        // l'exclusion de total_students dans /results.
        foreach ($ungradedStudents as $student) {
            Note::create([
                'value'        => 12,
                'status'       => NoteStatus::Presente,
                'is_published' => false,
                'published_at' => null,
                'student_id'   => $student->id,
                'subject_id'   => $algo->id,
                'session_id'   => $currentSession->id,
                'created_by'   => $author->user_id,
            ]);
        }

        // Une absence justifiée, pour couvrir les valeurs de NoteStatus dans
        // les données de démonstration. Portée par un étudiant "non gradé"
        // pour ne pas dupliquer une note (student, subject, session) déjà
        // créée ci-dessus — une absence justifiée n'a de toute façon aucun
        // impact sur la moyenne (cf. Note::effectiveValue()).
        if (isset($ungradedStudents[0])) {
            Note::create([
                'value'        => null,
                'status'       => NoteStatus::AbsJustifiee,
                'is_published' => true,
                'published_at' => now()->subDays(3),
                'student_id'   => $ungradedStudents[0]->id,
                'subject_id'   => $bdd->id,
                'session_id'   => $currentSession->id,
                'created_by'   => $author->user_id,
            ]);
        }

        // Notes sur la session passée : publiées maintenant, elles passeront
        // à LOCKED une fois la session verrouillée (cf. run()).
        foreach (array_slice($gradedStudents, 0, 4) as $student) {
            $this->createPublishedNote($student, $algo, $pastSession, $author, 13, daysAgo: 200);
        }
    }

    private function createPublishedNote(
        Student $student,
        Subject $subject,
        ExamSession $session,
        Teacher $author,
        float $value,
        int $daysAgo,
    ): Note {
        $note = Note::create([
            'value'        => $value,
            'status'       => NoteStatus::Presente,
            'is_published' => true,
            'published_at' => now()->subDays($daysAgo),
            'student_id'   => $student->id,
            'subject_id'   => $subject->id,
            'session_id'   => $session->id,
            'created_by'   => $author->user_id,
        ]);

        // Étale created_at sur plusieurs jours pour pouvoir tester le filtre
        // from_date/to_date de /stats. saveQuietly() évite de redéclencher
        // NoteObserver::updating() pour ce simple ajustement de date.
        $note->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();

        return $note;
    }

    /**
     * @param  array<int, Subject>  $subjects
     * @param  array<int, Student>  $students
     */
    private function seedNotifications(Admin $admin, array $subjects, array $students): void
    {
        $activities = [
            [
                'title'       => 'Nouvelle matière ajoutée',
                'description' => "La matière « {$subjects[0]->name} » a été ajoutée.",
                'type'        => NotificationType::NewSubject,
                'is_read'     => true,
                'daysAgo'     => 6,
            ],
            [
                'title'       => 'Nouvel étudiant inscrit',
                'description' => "L'étudiant « {$students[0]->first_name} {$students[0]->last_name} » a été inscrit.",
                'type'        => NotificationType::NewStudent,
                'is_read'     => true,
                'daysAgo'     => 5,
            ],
            [
                'title'       => 'Notes importées',
                'description' => 'Un import de notes a été effectué pour la session normale 2025-2026.',
                'type'        => NotificationType::NoteImportation,
                'is_read'     => false,
                'daysAgo'     => 3,
            ],
            [
                'title'       => 'Notes publiées',
                'description' => 'Les notes de la session normale 2025-2026 ont été publiées.',
                'type'        => NotificationType::NotePublished,
                'is_read'     => false,
                'daysAgo'     => 2,
            ],
            [
                'title'       => 'Session verrouillée',
                'description' => 'La session normale 2024-2025 a été verrouillée.',
                'type'        => NotificationType::NoteLocked,
                'is_read'     => false,
                'daysAgo'     => 0,
            ],
        ];

        foreach ($activities as $activity) {
            Notification::create([
                'title'       => $activity['title'],
                'description' => $activity['description'],
                'type'        => $activity['type'],
                'is_read'     => $activity['is_read'],
                'admin_id'    => $admin->id,
            ])->forceFill(['created_at' => now()->subDays($activity['daysAgo'])])->saveQuietly();
        }
    }
}
