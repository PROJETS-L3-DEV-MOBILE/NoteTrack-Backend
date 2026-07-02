<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Student;
use App\Models\UE;

class GradeCalculatorService
{
    // RG03 ne fixe un seuil que par matière ; ce seuil UE par défaut n'est pas
    // décrit dans le cahier des charges et doit être confirmé avant activation.
    private const DEFAULT_UE_THRESHOLD = 10.0;

    /**
     * Moyenne d'une UE pour un étudiant dans une session ("Moyenne UE" du bulletin) :
     * moyenne pondérée par coefficient des notes publiées des matières de l'UE.
     */
    public function ueAverage(Student $student, UE $ue, ExamSession $session): ?float
    {
        $subjects = $ue->subjects()->where('is_available', true)->get();

        $totalPoints = 0;
        $totalCoefficients = 0;

        foreach ($subjects as $subject) {
            $note = $student->notes()
                ->where('subject_id', $subject->id)
                ->where('session_id', $session->id)
                ->where('is_published', true) // RG04
                ->first();

            /** @var \App\Models\Note|null $note */
            if (! $note) {
                continue;
            }

            $value = $note->effectiveValue();

            if ($value === null) { // abs justifiée (RG10), non comptée
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
     * calculée directement sur les matières publiées de la session.
     *
     * Fix #5 : l'ancienne version passait par une moyenne intermédiaire par UE
     * pondérée par les crédits d'UE (deux niveaux), ce qui ne correspond pas
     * à la formule littérale de RG02 et cassait dès qu'un étudiant avait une
     * note dans une matière sans passer par ueAverage() correctement alimentée.
     *
     * ⚠️ RG02 précise aussi : "seules les matières validées (note ≥ seuil)
     * sont incluses selon politique universitaire". Je n'ai PAS appliqué ce
     * filtre ici : sur le relevé de notes fourni en exemple, toutes les
     * matières du semestre sont validées, donc je n'ai aucun moyen de vérifier
     * le comportement attendu quand une matière est en échec. Exclure les
     * échecs du calcul gonflerait la moyenne d'un étudiant en difficulté —
     * un choix qui doit être validé explicitement avec votre encadrant avant
     * d'être activé. Le statut de validation par matière reste disponible via
     * Note::isValidated() si vous décidez de l'appliquer.
     */
    public function generalAverage(Student $student, ExamSession $session): ?float
    {
        $notes = $student->notes()
            ->where('session_id', $session->id)
            ->where('is_published', true) // RG04
            ->with('subject')
            ->get();

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Note> $notes */
        $totalPoints = 0;
        $totalCoefficients = 0;

        foreach ($notes as $note) {
            $value = $note->effectiveValue();

            if ($value === null) {
                continue;
            }

            $totalPoints += $value * $note->subject->coefficient;
            $totalCoefficients += $note->subject->coefficient;
        }

        if ($totalCoefficients === 0) {
            return null;
        }

        return round($totalPoints / $totalCoefficients, 2);
    }

    /**
     * Statut "Résultat UE" du bulletin.
     */
    public function ueValidated(Student $student, UE $ue, ExamSession $session): ?bool
    {
        $average = $this->ueAverage($student, $ue, $session);

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
