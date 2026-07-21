<?php

namespace App\Services;

use App\Enums\NoteStatus;
use App\Models\Teacher;

class TeacherSubjectsService
{
  /**
   * Get teacher subjects grouped by class.
   */
  public function getGroupedSubjects(Teacher $teacher): array
  {
    // 1. Get subjects and load its relations
    $subjects = $teacher->subjects()
      ->with([
        'ue.classe.students',
        'semester',
        'notes',
      ])
      ->get();

    // 2. Group subjects by class (via UEs)
    $groupedByClasse = $subjects->groupBy(function ($subject) {
      return $subject->ue?->classe_id;
    });

    $result = [];

    foreach ($groupedByClasse as $classeId => $teacherSubjects) {
      $firstSubject = $teacherSubjects->first();
      $classe = $firstSubject?->ue?->classe;

      if (!$classe) {
        continue;
      }

      $studentsCount = $classe->students->count();

      $formattedSubjects = $teacherSubjects->map(function ($subject) use ($studentsCount) {

        $semesterNumber = $subject->semester?->id;

        $publishedNotesCount = $subject->notes
          ->where('status', NoteStatus::Published)
          ->count();

        return [
          'id'                    => $subject->id,
          'name'                  => $subject->name,
          'ue'                    => $subject->ue?->label,
          'semester_number'       => $semesterNumber,
          'credits'               => $subject->credits,
          'coefficient'           => $subject->coefficient,
          'threshold'             => $subject->threshold,
          'published_notes_count' => $publishedNotesCount,
          'total_notes_count'     => $studentsCount,
        ];
      })->values()->toArray();

      $result[] = [
        'classe_id'      => $classe->id,
        'classe_label'   => $classe->label,
        'students_count' => $studentsCount,
        'subjects_count' => $teacherSubjects->count(),
        'subjects'       => $formattedSubjects,
      ];
    }

    return $result;
  }
}
