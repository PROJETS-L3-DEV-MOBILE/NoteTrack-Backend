<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\{User, Student, Subject, Admin};
use App\Notifications\SystemNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationService
{

  public function notifyStudentCreated(User $actor, Student $student): void
  {
    $fullName = "{$student->first_name} {$student->last_name}";

    $actor->notify(new SystemNotification(
      title: "Étudiant ajouté",
      description: "Vous avez ajouté l'étudiant {$fullName}.",
      type: NotificationType::NewStudent
    ));

    $otherAdmins = User::where('role', 'admin')
      ->where('id', '!=', $actor->id)
      ->get();

    Notification::send($otherAdmins, new SystemNotification(
      title: "Nouvel étudiant ajouté",
      description: "{$actor->username} a ajouté l'étudiant {$fullName}.",
      type: NotificationType::NewStudent
    ));
  }

  public function notifySubjectCreated(User $actor, Subject $subject): void
  {
    $actor->notify(new SystemNotification(
      title: "Matière créée",
      description: "Vous avez créé la matière {$subject->name}.",
      type: NotificationType::NewSubject
    ));

    $otherAdmins = User::where('role', 'admin')
      ->where('id', '!=', $actor->id)
      ->get();

    Notification::send($otherAdmins, new SystemNotification(
      title: "Nouvelle matière",
      description: "{$actor->username} a ajouté la matière {$subject->name}.",
      type: NotificationType::NewSubject
    ));
  }

  public function notifyNotesPublished(User $actor, Subject $subject, int $count, Collection $targetStudents): void
  {
    if ($count <= 0) return;

    $actor->notify(new SystemNotification(
      title: "Notes publiées",
      description: "Vous avez publié {$count} note(s) pour la matière {$subject->name}.",
      type: NotificationType::NotePublished
    ));

    if ($targetStudents->isNotEmpty()) {
      Notification::send($targetStudents, new SystemNotification(
        title: "Nouvelle note disponible",
        description: "Vos notes pour la matière {$subject->name} ont été publiées.",
        type: NotificationType::NotePublished
      ));
    }

    $otherAdmins = User::where('role', 'admin')
      ->where('id', '!=', $actor->id)
      ->get();

    if ($otherAdmins->isNotEmpty()) {
      Notification::send($otherAdmins, new SystemNotification(
        title: "Publication de notes",
        description: "{$actor->username} a publié {$count} note(s) pour la matière {$subject->name}.",
        type: NotificationType::NotePublished
      ));
    }
  }

  public function notifyNotesLocked(User $actor, Subject $subject, int $count, Collection $targetStudents): void
  {
    if ($count <= 0) {
      return;
    }

    $actor->notify(new SystemNotification(
      title: "Notes verrouillées",
      description: "Vous avez verrouillé {$count} note(s) pour la matière {$subject->name}.",
      type: NotificationType::NoteLocked
    ));

    if ($targetStudents->isNotEmpty()) {
      Notification::send($targetStudents, new SystemNotification(
        title: "Notes verrouillées",
        description: "Les notes de la matière {$subject->name} sont désormais définitivement verrouillées.",
        type: NotificationType::NoteLocked
      ));
    }

    $otherAdmins = User::where('role', 'admin')
      ->where('id', '!=', $actor->id)
      ->get();

    if ($otherAdmins->isNotEmpty()) {
      Notification::send($otherAdmins, new SystemNotification(
        title: "Verrouillage de notes",
        description: "{$actor->username} a verrouillé {$count} note(s) dans la matière {$subject->name}.",
        type: NotificationType::NoteLocked
      ));
    }
  }
}
