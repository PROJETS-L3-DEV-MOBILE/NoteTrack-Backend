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
        return $this->isTeacherOfSubject($user, $subject);
    }

    public function view(User $user, Note $note): bool
    {
        $isTeacherOfSubject = $note->subject && $this->isTeacherOfSubject($user, $note->subject);
        $isStudentOwner = $user->role === 'student' && $note->student_id === $user->student?->id;

        return $isTeacherOfSubject || $isStudentOwner;
    }

    public function create(User $user, Subject $subject): bool
    {
        return $this->isTeacherOfSubject($user, $subject);
    }

    public function manageNotes(User $user, Subject $subject): bool
    {
        return $this->isTeacherOfSubject($user, $subject);
    }

    public function update(User $user, Note $note): bool
    {
        if (!$note->subject) {
            return false;
        }

        return $this->isTeacherOfSubject($user, $note->subject);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->update($user, $note);
    }

    private function isTeacherOfSubject(User $user, Subject $subject): bool
    {
        return $user->role === 'teacher' && $subject->teacher_id === $user->teacher?->id;
    }
}
