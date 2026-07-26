<?php

namespace App\Services;

use App\Enums\NoteType;
use App\Models\Student;
use App\Models\Subject;
use App\Models\UE;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;

/**
 * Construit les données du relevé de notes (bulletin) d'un étudiant et
 * produit le PDF correspondant, en s'appuyant exclusivement sur
 * GradeCalculatorService pour tous les calculs (RG02/RG03/RG04/RG06), afin
 * de ne jamais diverger des moyennes déjà affichées ailleurs dans l'app
 * (dashboard, StudentResource::average, etc.).
 */
class TranscriptService
{
    public function __construct(protected GradeCalculatorService $gradeCalculator) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Student $student, ?int $schoolYearId, ?int $semesterId): array
    {
        $student->loadMissing([
            'user',
            'promotion.schoolYear',
            'classe.ues.subjects.teacher',
            'classe.ues.subjects.semester',
            'notes',
        ]);

        $ues = $student->classe->ues
            ->map(function (UE $ue) use ($student, $semesterId) {
                $subjects = $ue->subjects
                    ->filter(fn (Subject $subject) => $subject->is_available)
                    ->when($semesterId, fn (Collection $subjects) => $subjects->where('semester_id', $semesterId))
                    ->values()
                    ->map(fn (Subject $subject) => $this->buildSubjectRow($student, $subject));

                if ($subjects->isEmpty()) {
                    return null;
                }

                return [
                    'id'         => $ue->id,
                    'code'       => $ue->code,
                    'label'      => $ue->label,
                    'subjects'   => $subjects,
                    'ue_average' => $this->gradeCalculator->ueAverage($student, $ue, $semesterId),
                    'ue_validated' => $this->gradeCalculator->ueValidated($student, $ue, $semesterId),
                    'total_credits' => $subjects->sum(fn ($row) => $row['credits']),
                ];
            })
            ->filter()
            ->values();

        $generalAverage = $this->gradeCalculator->generalAverage($student, $semesterId);

        return [
            'student'         => $student,
            'promotion'       => $student->promotion,
            'classe'          => $student->classe,
            'school_year'     => $student->promotion?->schoolYear,
            'semester_id'     => $semesterId,
            'ues'             => $ues,
            'general_average' => $generalAverage,
            'mention'         => $this->gradeCalculator->mention($generalAverage),
            'generated_at'    => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubjectRow(Student $student, Subject $subject): array
    {
        // $student->notes est déjà eager-loadée en amont (build()) : on
        // filtre en mémoire plutôt que de refaire une requête par matière.
        $notes = $student->notes
            ->where('subject_id', $subject->id)
            ->keyBy(fn ($note) => $note->type->value);

        $noteFor = function (NoteType $type) use ($notes) {
            $note = $notes->get($type->value);

            if ($note === null) {
                return null;
            }

            return [
                'value'  => $note->status->value === 'PENDING' ? null : $note->effectiveValue(),
                'status' => $note->status->value,
            ];
        };

        $average = $this->gradeCalculator->subjectAverage($student, $subject);

        return [
            'id'          => $subject->id,
            'name'        => $subject->name,
            'teacher'     => $subject->teacher ? "{$subject->teacher->first_name} {$subject->teacher->last_name}" : null,
            'coefficient' => $subject->coefficient,
            'credits'     => $subject->credits,
            'threshold'   => (float) $subject->threshold,
            'test'        => $noteFor(NoteType::Test),
            'exam'        => $noteFor(NoteType::Exam),
            'makeup'      => $noteFor(NoteType::Makeup),
            'average'     => $average,
            // RG03 : matière validée si la moyenne effective atteint le seuil de la matière.
            'validated'   => $average === null ? null : $average >= (float) $subject->threshold,
        ];
    }

    public function render(array $data): DomPdf
    {
        return Pdf::loadView('pdf.transcript', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans');
    }
}
