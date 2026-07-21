<?php

namespace App\Services;

use App\Enums\NoteStatus;
use App\Models\Teacher;
use Carbon\Carbon;

class TeacherDashboardService
{
  public function getStats(Teacher $teacher, ?string $fromDate = null, ?string $toDate = null): array
  {
    $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : null;
    $to   = $toDate ? Carbon::parse($toDate)->endOfDay() : null;

    // 1. subjects count
    $taughtSubjectsCount = $teacher->subjects()->count();

    // 2. distinct students count
    $studentsCount = $teacher->students()->distinct()->count('students.id');

    // 4. Notes by status
    $notesQuery = $teacher->notes()
      ->when($from && $to, fn($q) => $q->whereBetween('notes.created_at', [$from, $to]));

    $publishedNotesCount = (clone $notesQuery)->where('notes.status', NoteStatus::Published)->count();
    $pendingNotesCount   = (clone $notesQuery)->where('notes.status', NoteStatus::Pending)->count();

    return [
      'taught_subjects'        => $taughtSubjectsCount,
      'participating_students' => $studentsCount,
      'published_notes'        => $publishedNotesCount,
      'pending_notes'          => $pendingNotesCount,
    ];
  }
}
