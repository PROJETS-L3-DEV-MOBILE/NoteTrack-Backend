<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\Subject;
use App\Models\User;

class NotePolicy
{

    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        return null;
    }

    public function viewBySubject(User $user, Subject $subject): bool
    {
        return $subject->teacher_id === $user->id;
    }

    public function view(User $user, Note $note): bool
    {
        $isTeacherOwner = $note->subject?->teacher_id === $user->id;
        $isStudentOwner = $user->student_id === $note->student_id;

        return $isTeacherOwner || $isStudentOwner;
    }

    public function create(User $user, Subject $subject): bool
    {
        return $subject->teacher_id === $user->id;
    }

    public function manageNotes(User $user, Subject $subject): bool
    {
        return $subject->teacher_id === $user->id;
    }

    public function update(User $user, Note $note): bool
    {
        if ($note->subject?->teacher_id !== $user->id) {
            return false;
        }

        return $note->student?->classe_id === $note->subject->classe?->id;
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->update($user, $note);
    }
}
