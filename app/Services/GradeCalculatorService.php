<?php

namespace App\Services;

use App\Enums\NoteStatus;
use App\Enums\NoteType;
use App\Models\Note;
use App\Models\Student;
use App\Models\Subject;
use App\Models\UE;
use Illuminate\Support\Collection;

class GradeCalculatorService
{
    // RG03 ne fixe un seuil que par matière ; ce seuil UE par défaut n'est pas
    // décrit dans le cahier des charges et doit être confirmé avant activation.
    private const DEFAULT_UE_THRESHOLD = 10.0;

    private function subjectEffectiveValue(Student $student, Subject $subject): ?float
    {
        $notes = $student->notes()
            ->where('subject_id', $subject->id)
            ->where('status', NoteStatus::Published) // RG04
            ->get()
            ->keyBy(fn (Note $note) => $note->type->value);

        $makeup = $notes->get(NoteType::Makeup->value);

        if ($makeup) {
            return $makeup->effectiveValue();
        }

        $test = $notes->get(NoteType::Test->value);
        $exam = $notes->get(NoteType::Exam->value);

        if (! $test || ! $exam) {
            return null; // matière pas encore entièrement évaluée/publiée
        }

        $testValue = $test->effectiveValue();
        $examValue = $exam->effectiveValue();

        if ($testValue === null || $examValue === null) {
            return null; // abs justifiée (RG10) sur une des deux composantes
        }

        return round(($testValue + $examValue) / 2, 2);
    }

    /**
     * Toutes les matières disponibles rattachées, via leur UE, à la classe de
     * l'étudiant (Student::classe_id / Student::classe()).
     */
    private function studentSubjects(Student $student): Collection
    {
        return Subject::query()
            ->where('is_available', true)
            ->whereHas('ue', fn ($q) => $q->where('classe_id', $student->classe_id))
            ->get();
    }

    /**
     * Moyenne d'une UE pour un étudiant ("Moyenne UE" du bulletin) : moyenne
     * pondérée par coefficient des notes effectives des matières de l'UE.
     */
    public function ueAverage(Student $student, UE $ue): ?float
    {
        $subjects = $ue->subjects()->where('is_available', true)->get();

        $totalPoints = 0;
        $totalCoefficients = 0;

        foreach ($subjects as $subject) {
            $value = $this->subjectEffectiveValue($student, $subject);

            if ($value === null) {
                continue;
            }

            $totalPoints += $value * $subject->coefficient;
            $totalCoefficients += $subject->coefficient;
        }

        if ($totalCoefficients === 0) {
            return null;
        }

        return round($totalPoints / $totalCoefficients, 2);
    }

    /**
     * Moyenne générale (RG02) : MG = Σ(note × coefficient) / Σ(coefficients),
     * calculée sur les matières disponibles de la classe de l'étudiant.
     */
    public function generalAverage(Student $student): ?float
    {
        $subjects = $this->studentSubjects($student);

        $totalPoints = 0;
        $totalCoefficients = 0;

        foreach ($subjects as $subject) {
            $value = $this->subjectEffectiveValue($student, $subject);

            if ($value === null) {
                continue;
            }

            $totalPoints += $value * $subject->coefficient;
            $totalCoefficients += $subject->coefficient;
        }

        if ($totalCoefficients === 0) {
            return null;
        }

        return round($totalPoints / $totalCoefficients, 2);
    }

    /**
     * Statut "Résultat UE" du bulletin.
     */
    public function ueValidated(Student $student, UE $ue): ?bool
    {
        $average = $this->ueAverage($student, $ue);

        if ($average === null) {
            return null;
        }

        return $average >= self::DEFAULT_UE_THRESHOLD;
    }

    // Mention à partir de la moyenne générale (RG06)
    public function mention(?float $average): string
    {
        if ($average === null) {
            return 'Non calculable';
        }

        return match (true) {
            $average < 10 => 'Ajourné',
            $average < 12 => 'Passable',
            $average < 14 => 'Assez bien',
            $average < 16 => 'Bien',
            default       => 'Très bien',
        };
    }
}
