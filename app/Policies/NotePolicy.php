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

    public function viewBySubject(User $user, string $subjectId): bool
    {
        $subject = Subject::find($subjectId);
        return $subject && $subject->teacher_id === $user->id;
    }


    public function view(User $user, Note $note): bool
    {
        $isTeacherOwner = $note->subject && $note->subject->teacher_id === $user->id;
        $isStudentOwner = $user->student_id === $note->student_id;

        return $isTeacherOwner || $isStudentOwner;
    }

    public function update(User $user, Note $note): bool
    {
        return $note->subject && $note->subject->teacher_id === $user->id;
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->update($user, $note);
    }
}
