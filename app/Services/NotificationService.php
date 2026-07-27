<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\{User, Student, Subject, NoteImport, Teacher};
use App\Notifications\SystemNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationService
{

  public function notifyStudentCreated(User $actor, Student $student): void
  {
    $fullName = "{$student->first_name} {$student->last_name}";

    Notification::send($actor, new SystemNotification(
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
    Notification::send($actor, new SystemNotification(
      title: "Matière créée",
      description: "Vous avez créé la matière {$subject->name}.",
      type: NotificationType::NewSubject
    ));

    $otherAdmins = User::where('role', 'admin')
      ->where('id', '!=', $actor->id)
      ->get();

    Notification::send($otherAdmins, new SystemNotification(
      title: "Nouvelle matière",
      description: "{$actor->profile->username} a ajouté la matière {$subject->name}.",
      type: NotificationType::NewSubject
    ));


    $teacher = Teacher::findOrFail($subject->teacher_id);
    $assignedTeacherUser = $teacher->user;

    if ($assignedTeacherUser) {
      $assignedTeacherUser->notify(
        new SystemNotification(
          title: "Nouvelle matière assignée",
          description: "Vous avez été assigné à la matière {$subject->name}.",
          type: NotificationType::NewSubject
        )
      );
    }
  }

  public function notifyNotesPublished(User $actor, Subject $subject, int $count, Collection $targetStudents): void
  {
    if ($count <= 0) return;

    Notification::send($actor, new SystemNotification(
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
        description: "{$actor->profile->username} a publié {$count} note(s) pour la matière {$subject->name}.",
        type: NotificationType::NotePublished
      ));
    }
  }

  public function notifyNotesLocked(User $actor, Subject $subject, int $count, Collection $targetStudents): void
  {
    if ($count <= 0) {
      return;
    }

    Notification::send($actor, new SystemNotification(
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
        description: "{$actor->profile->username} a verrouillé {$count} note(s) dans la matière {$subject->name}.",
        type: NotificationType::NoteLocked
      ));
    }
  }

  /**
   * Notifie l'admin qui a lancé un import CSV de notes une fois que le
   * worker (ProcessNoteImportJob) a terminé le traitement, qu'il ait
   * réussi, partiellement échoué, ou totalement échoué.
   */
  public function notifyNoteImportFinished(NoteImport $import): void
  {
    $actor = $import->createdBy;

    if (! $actor) {
      return;
    }

    $description = match ($import->status->value) {
      'COMPLETED' => "Votre import \"{$import->original_filename}\" est terminé : {$import->imported_count} note(s) créée(s), {$import->updated_count} mise(s) à jour.",
      'COMPLETED_WITH_ERRORS' => "Votre import \"{$import->original_filename}\" est terminé avec {$import->failed_count} ligne(s) en erreur sur {$import->processed_rows}.",
      'FAILED' => "Votre import \"{$import->original_filename}\" a échoué. Consultez le détail des erreurs.",
      default => "Votre import \"{$import->original_filename}\" a été traité.",
    };

    Notification::send($actor, new SystemNotification(
      title: 'Import de notes terminé',
      description: $description,
      type: NotificationType::NoteImportation
    ));

    if ($import->status->value !== "COMPLETED") {
      return;
    }

    $otherAdmins = User::where('role', 'admin')
      ->where('id', '!=', $actor->id)
      ->get();

    if ($otherAdmins->isNotEmpty()) {
      Notification::send($otherAdmins, new SystemNotification(
        title: "Nouvelles notes importées",
        description: "{$actor->profile->username} a importé de nouvelles notes via fichier CSV.",
        type: NotificationType::NoteImportation
      ));
    }
  }
}
