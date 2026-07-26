<?php

namespace App\Services;

use App\Enums\Mention;
use App\Enums\NoteStatus;
use App\Enums\NoteType;
use App\Enums\SessionType;
use App\Models\Note;
use App\Models\Student;
use App\Models\Subject;
use App\Models\UE;
use Illuminate\Support\Collection;

class StudentHomeService
{
    public function __construct(
        protected GradeCalculatorService $gradeCalculator,
    ) {}

    /**
     * GET /student/home/stats — StudentHomeStats.
     */
    public function stats(Student $student, array $filters): array
    {
        $subjectIds = $this->subjectsQuery($student, $filters)->pluck('id');

        [$yearRequested, $schoolYearId] = $this->schoolYearScope($filters);

        $notesQuery = Note::where('student_id', $student->id)
            ->whereIn('subject_id', $subjectIds)
            ->when($yearRequested, fn ($q) => $q->where('school_year_id', $schoolYearId));

        return [
            'total_subjects'  => $subjectIds->count(),
            // "disponibles" = visibles par l'étudiant (RG : PUBLISHED/LOCKED).
            'available_notes' => (clone $notesQuery)
                ->whereIn('status', [NoteStatus::Published, NoteStatus::Locked])
                ->count(),
            // Comptage seul (pas de valeur exposée) : ne viole pas la règle
            // "seules PUBLISHED/LOCKED sont exposées à l'étudiant".
            'pending_notes' => (clone $notesQuery)
                ->where('status', NoteStatus::Pending)
                ->count(),
        ];
    }

    /**
     * GET /student/home/statistics — StudentStatistics.
     */
    public function statistics(Student $student, array $filters): array
    {
        if (! $this->isScopedToOwnClasse($student, $filters)) {
            return [
                'average'           => null,
                'mention'           => null,
                'rank'              => 0,
                'total_students'    => 0,
                'validated_credits' => 0,
            ];
        }

        $semesterId = isset($filters['semester_id']) ? (int) $filters['semester_id'] : null;

        $average = $this->gradeCalculator->generalAverage($student, $semesterId);
        $mention = $average === null ? null : $this->mentionFromAverage($average);

        ['rank' => $rank, 'total_students' => $totalStudents] = $this->rankFor($student, $semesterId);

        return [
            'average'           => $average,
            'mention'           => $mention,
            'rank'              => $rank,
            'total_students'    => $totalStudents,
            'validated_credits' => $this->validatedCredits($student, $semesterId),
        ];
    }

    /**
     * GET /student/results — StudentNotes (Array<StudentUECard>).
     */
    public function results(Student $student, array $filters): Collection
    {
        if (! $this->isScopedToOwnClasse($student, $filters)) {
            return collect();
        }

        $semesterId   = isset($filters['semester_id']) ? (int) $filters['semester_id'] : null;
        [$yearRequested, $schoolYearId] = $this->schoolYearScope($filters);
        $session      = isset($filters['session']) ? SessionType::from($filters['session']) : null;

        $ues = UE::where('classe_id', $student->classe_id)
            ->with(['subjects' => function ($query) use ($semesterId) {
                $query->where('is_available', true)
                    ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
                    ->with(['teacher', 'semester']);
            }])
            ->get();

        $ueCards = $ues->map(function (UE $ue) use ($student, $yearRequested, $schoolYearId, $session, $semesterId) {
            $subjects = $ue->subjects
                ->map(fn (Subject $subject) => $this->subjectMiniCard($student, $subject, $yearRequested, $schoolYearId, $session));

            if ($session === SessionType::Rattrapage) {
                // Onglet "Rattrapage" : seules les matières où l'étudiant a
                // effectivement une note de rattrapage visible ont du sens.
                $subjects = $subjects->filter(fn (array $s) => $s['notes']['makeup'] !== null)->values();
            }

            return [
                'id'               => $ue->id,
                'label'            => $ue->label,
                'color'            => $ue->color,
                'credits'          => $this->ueCredits($ue, $semesterId),
                // Schéma non-nullable : une UE pas encore évaluable est
                // considérée non validée (false), jamais null.
                'credits_obtained' => (bool) $this->gradeCalculator->ueValidated($student, $ue, $semesterId),
                'subjects'         => $subjects->values()->all(),
            ];
        });

        if ($session === SessionType::Rattrapage) {
            $ueCards = $ueCards->filter(fn (array $ue) => count($ue['subjects']) > 0)->values();
        }

        return $ueCards;
    }

    /**
     * Matières disponibles de la classe de l'étudiant, éventuellement
     * restreintes par le filtre `semester_id`.
     */
    private function subjectsQuery(Student $student, array $filters)
    {
        $query = Subject::query()->where('is_available', true);

        if (! $this->isScopedToOwnClasse($student, $filters)) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereHas('ue', fn ($q) => $q->where('classe_id', $student->classe_id));

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        return $query;
    }

    /**
     * `class_id`, quand fourni, doit correspondre à la classe réelle de
     * l'étudiant — voir la note de classe ci-dessus.
     */
    private function isScopedToOwnClasse(Student $student, array $filters): bool
    {
        $classId = $filters['class_id'] ?? null;

        return $classId === null || $classId === $student->classe_id;
    }

    /**
     * @return array{0: bool, 1: ?int} [$filtreDemandé, $schoolYearId]
     */
    private function schoolYearScope(array $filters): array
    {
        $schoolYearId = $filters['school_year_id'] ?? null;

        if ($schoolYearId === null) {
            return [false, null];
        }

        return [true, (int) $schoolYearId];
    }

    private function mentionFromAverage(float $average): Mention
    {
        return match (true) {
            $average < 10 => Mention::FAILED,
            $average < 12 => Mention::PASS,
            $average < 14 => Mention::SATISFACTORY,
            $average < 16 => Mention::GOOD,
            default       => Mention::EXCELLENT,
        };
    }

    /**
     * Classement de l'étudiant dans sa classe.
     * - Le classement porte sur tous les étudiants de la classe (pas
     *   seulement ceux ayant déjà une moyenne calculable).
     * - Classement "standard" : deux moyennes égales partagent le même rang.
     * - Un étudiant sans moyenne calculable est classé après tous ceux qui
     *   en ont une (moyenne traitée comme -∞ pour le tri).
     */
    private function rankFor(Student $student, ?int $semesterId): array
    {
        $classmates = Student::where('classe_id', $student->classe_id)->get();

        $averagesById = $classmates->mapWithKeys(
            fn (Student $s) => [$s->id => $this->gradeCalculator->generalAverage($s, $semesterId)]
        );

        $ordered = $averagesById->sortByDesc(fn (?float $avg) => $avg ?? -INF);

        $rank = 1;
        $position = 0;
        $previousAverage = null;

        foreach ($ordered as $studentId => $average) {
            $position++;

            if ($previousAverage === null || $average !== $previousAverage) {
                $rank = $position;
            }

            if ($studentId === $student->id) {
                break;
            }

            $previousAverage = $average;
        }

        return ['rank' => $rank, 'total_students' => $classmates->count()];
    }

    /**
     * Somme des crédits des UE validées (moyenne UE ≥ seuil, RG-derived) —
     * "Crédits validés" de StudentStatistics.
     */
    private function validatedCredits(Student $student, ?int $semesterId): int
    {
        $ues = UE::where('classe_id', $student->classe_id)->get();

        $credits = 0;

        foreach ($ues as $ue) {
            if ($this->gradeCalculator->ueValidated($student, $ue, $semesterId)) {
                $credits += $this->ueCredits($ue, $semesterId);
            }
        }

        return $credits;
    }

    private function ueCredits(UE $ue, ?int $semesterId): int
    {
        return (int) $ue->subjects()
            ->where('is_available', true)
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->sum('credits');
    }

    /**
     * StudentSubjectMiniCard : matière + ses 3 notes (test/exam/makeup),
     * chacune null si non saisie ou non visible (PENDING) par l'étudiant.
     */
    private function subjectMiniCard(Student $student, Subject $subject, bool $yearRequested, ?int $schoolYearId, ?SessionType $session): array
    {
        $notes = Note::where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            // RG : seules les notes PUBLISHED/LOCKED sont exposées à l'étudiant.
            ->whereIn('status', [NoteStatus::Published, NoteStatus::Locked])
            ->when($yearRequested, fn ($q) => $q->where('school_year_id', $schoolYearId))
            ->get()
            ->keyBy(fn (Note $note) => $note->type->value);

        $noteFor = function (NoteType $type) use ($notes, $session) {
            // Filtre "session" (onglets Tout/Normale/Rattrapage) : Normale =
            // test+exam, Rattrapage = makeup. Absent = tout affiché.
            if ($session === SessionType::Normale && $type === NoteType::Makeup) {
                return null;
            }

            if ($session === SessionType::Rattrapage && $type !== NoteType::Makeup) {
                return null;
            }

            $note = $notes->get($type->value);

            if ($note === null) {
                return null;
            }

            return [
                'id'     => $note->id,
                'value'  => $note->value === null ? null : (float) $note->value,
                'status' => $note->status->value,
            ];
        };

        return [
            'id'          => $subject->id,
            'name'        => $subject->name,
            'credits'     => $subject->credits,
            'coefficient' => $subject->coefficient,
            'threshold'   => (float) $subject->threshold,
            // Convention déjà en place côté TeacherSubjectsService : l'id du
            // semestre sert directement de "numéro" (seeder 1..10 séquentiel).
            'semester'    => ['semester_number' => $subject->semester_id],
            'teacher'     => ['display_name' => $subject->teacher?->display_name],
            'notes'       => [
                'test'   => $noteFor(NoteType::Test),
                'exam'   => $noteFor(NoteType::Exam),
                'makeup' => $noteFor(NoteType::Makeup),
            ],
        ];
    }
}
