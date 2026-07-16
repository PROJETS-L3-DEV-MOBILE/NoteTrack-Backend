<?php

namespace App\Enums;

enum NotificationType: string
{
    case NoteImportation = 'NOTE_IMPORTATION';
    case NewStudent      = 'NEW_STUDENT';
    case NewSubject      = 'NEW_SUBJECT';
    case NotePublished   = 'NOTE_PUBLISHED';
    case NoteLocked      = 'NOTE_LOCKED';

    public function label(): string
    {
        return match ($this) {
            self::NoteImportation => 'Importation de notes',
            self::NewStudent      => 'Nouvel étudiant',
            self::NewSubject      => 'Nouvelle matière',
            self::NotePublished   => 'Notes publiées',
            self::NoteLocked      => 'Notes verrouillées',
        };
    }
}
