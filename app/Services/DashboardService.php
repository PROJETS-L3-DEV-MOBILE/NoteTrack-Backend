<?php

namespace App\Services;

use App\Enums\NoteStatus;
use App\Models\Admin;
use App\Models\Note;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
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

            // Fix (suppression ExamSession) : "publiée" se lit maintenant sur
            // Note::status plutôt que sur une colonne is_published inexistante.
            'published_notes' => $this->applyDateRange(
                Note::query()->where('status', NoteStatus::Published),
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
     * Fix (suppression ExamSession) : il n'y a plus de session d'examen à
     * résoudre — GradeCalculatorService calcule directement sur les matières
     * de la classe de l'étudiant.
     *
     * Hypothèses :
     * - "school_year" filtre sur Promotion::prom_year, par cohérence avec
     *   StudentController/StudentResource qui utilisent déjà ce champ sous ce
     *   même nom "school_year" (et non le nouveau modèle SchoolYear, qui
     *   n'est pour l'instant référencé que par Note::school_year_id et
     *   Promotion::school_year_id, sans usage de filtrage ailleurs dans le
     *   code). À confirmer si le nouveau modèle SchoolYear doit à terme
     *   remplacer Promotion::prom_year partout, dashboard inclus.
     * - "level" filtre sur le libellé de la classe (Classe::label) via
     *   Student::classe_id / Student::classe().
     * - "classe_id" filtre directement sur Student::classe_id.
     * - Seuls les étudiants ayant une moyenne calculable (donc "évalués")
     *   sont comptabilisés dans total_students.
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

        $students = $this->studentsQuery($level, $classId, $schoolYear)->get();

        foreach ($students as $student) {
            $average = $this->gradeCalculator->generalAverage($student);

            if ($average === null) {
                continue; // pas encore évalué
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
     * Activités récentes non lues (RecentActivitiesSection), basées sur le
     * système de notifications natif de Laravel
     * (App\Notifications\DashboardActivityNotification, envoyées à des Admin
     * via ->notify()).
     *
     * Le flux n'est pas propre à un admin précis (vue globale du dashboard),
     * d'où la lecture directe sur DatabaseNotification plutôt que via la
     * relation $admin->notifications() d'une seule instance.
     *
     * Seules les notifications non lues (read_at null) sont retournées.
     */
    public function recentActivities(int $limit = 10): Collection
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', Admin::class)
            ->whereNull('read_at')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Dernières notes saisies (NotesTable), basées sur Note + Student.
     *
     * Fix (suppression ExamSession) : l'eager-load 'session' est retiré, Note
     * n'a plus de relation vers une session d'examen.
     */
    public function latestNotes(int $limit = 10): Collection
    {
        return Note::query()
            ->with(['student.user', 'subject.teacher.user'])
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

    private function studentsQuery(?string $level, ?string $classId, ?string $schoolYear): Builder
    {
        return Student::query()
            ->when($classId, fn (Builder $q) => $q->where('classe_id', $classId))
            ->when($level, fn (Builder $q) => $q->whereHas(
                'classe',
                fn (Builder $q) => $q->where('label', $level),
            ))
            ->when($schoolYear, fn (Builder $q) => $q->whereHas(
                'promotion',
                fn (Builder $q) => $q->where('prom_year', $schoolYear),
            ));
    }

    private function applyDateRange(Builder $query, string $column, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->where($column, '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn (Builder $q) => $q->where($column, '<=', Carbon::parse($to)->endOfDay()));
    }
}