<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        protected GradeCalculatorService $gradeCalculator,
    ) {}

    /**
     * Cartes de statistiques du dashboard (AdminDashboardStats).
     *
     * Le filtre de dates s'applique à la date d'enregistrement (created_at)
     * de chaque ressource, sauf :
     * - published_notes, où published_at est la date pertinente ;
     * - active_teachers, qui reflète l'état actuel (indépendant de la
     *   période filtrée) et n'est donc pas affecté par from_date/to_date.
     */
    public function stats(?string $fromDate, ?string $toDate): array
    {
        return [
            'registered_subjects' => $this->applyDateRange(Subject::query(), 'created_at', $fromDate, $toDate)->count(),

            'enrolled_students' => $this->applyDateRange(Student::query(), 'created_at', $fromDate, $toDate)->count(),

            // Un enseignant est considéré "actif" s'il a au moins une matière
            // disponible qui lui est affectée. Critère non défini explicitement
            // dans le cahier des charges : à valider avec l'équipe produit.
            'active_teachers' => Teacher::query()
                ->whereHas('subjects', fn (Builder $q) => $q->where('is_available', true))
                ->count(),

            'published_notes' => $this->applyDateRange(
                Note::query()->where('is_published', true),
                'published_at',
                $fromDate,
                $toDate,
            )->count(),
        ];
    }

    /**
     * Répartition des résultats (ResultChart), calculée à partir de la
     * moyenne générale de chaque étudiant (GradeCalculatorService) et
     * catégorisée via les mêmes seuils que GradeCalculatorService::mention().
     *
     * Hypothèses (non explicitées dans le cahier des charges) :
     * - "school_year" filtre sur ExamSession::year ; à défaut, la session
     *   d'examen la plus récemment créée est utilisée.
     * - "level" filtre sur le libellé de la classe (Classes::label) associée
     *   à la promotion de l'étudiant ; "class_id" filtre directement sur
     *   l'identifiant de cette classe.
     * - Seuls les étudiants ayant une moyenne calculable sur la session
     *   retenue (donc "évalués") sont comptabilisés dans total_students.
     */
    public function results(?string $level, ?string $classId, ?string $schoolYear): array
    {
        $buckets = [
            'failed'       => 0,
            'pass'         => 0,
            'satisfactory' => 0,
            'good'         => 0,
            'excellent'    => 0,
        ];

        $session = $this->resolveSession($schoolYear);

        if (! $session) {
            return [...$buckets, 'total_passed' => 0, 'total_students' => 0];
        }

        $students = $this->studentsQuery($level, $classId)->get();

        foreach ($students as $student) {
            $average = $this->gradeCalculator->generalAverage($student, $session);

            if ($average === null) {
                continue; // pas encore évalué sur cette session
            }

            match ($this->gradeCalculator->mention($average)) {
                'Ajourné'    => $buckets['failed']++,
                'Passable'   => $buckets['pass']++,
                'Assez bien' => $buckets['satisfactory']++,
                'Bien'       => $buckets['good']++,
                'Très bien'  => $buckets['excellent']++,
                default      => null,
            };
        }

        $totalStudents = array_sum($buckets);

        return [
            ...$buckets,
            'total_passed'   => $totalStudents - $buckets['failed'],
            'total_students' => $totalStudents,
        ];
    }

    /**
     * Activités récentes (RecentActivitiesSection), basées sur Notification.
     */
    public function recentActivities(int $limit = 10): Collection
    {
        return Notification::query()
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Dernières notes saisies (NotesTable), basées sur Note + Student.
     */
    public function latestNotes(int $limit = 10): Collection
    {
        return Note::query()
            ->with(['student.user', 'subject.teacher.user', 'session'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Dernières matières ajoutées (RecentSubjectsTable), basées sur Subject.
     */
    public function recentSubjects(int $limit = 10): Collection
    {
        return Subject::query()
            ->with(['teacher.user', 'admin.user'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    private function resolveSession(?string $schoolYear): ?ExamSession
    {
        // Fix : deux sessions créées lors du même seed (donc au même created_at
        // à la seconde près) rendaient ce tri instable — ->latest('created_at')
        // seul ne garantit aucun ordre en cas d'égalité, et pouvait donc
        // retourner une session plus ancienne que prévu comme "la plus
        // récente". Les uuid générés par HasUuids étant ordonnés par date de
        // création (à la microseconde près), trier aussi par id lève
        // l'ambiguïté de façon fiable.
        return ExamSession::query()
            ->when($schoolYear, fn (Builder $q) => $q->where('year', $schoolYear))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function studentsQuery(?string $level, ?string $classId): Builder
    {
        return Student::query()
            ->when($classId, fn (Builder $q) => $q->whereHas(
                'promotion.classes',
                fn (Builder $q) => $q->where('class_id', $classId),
            ))
            ->when($level, fn (Builder $q) => $q->whereHas(
                'promotion.classes.classe',
                fn (Builder $q) => $q->where('label', $level),
            ));
    }

    private function applyDateRange(Builder $query, string $column, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where($column, '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn (Builder $q) => $q->where($column, '<=', Carbon::parse($to)->endOfDay()));
    }
}