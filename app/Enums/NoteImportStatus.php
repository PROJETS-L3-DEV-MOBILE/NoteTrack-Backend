<?php

namespace App\Enums;

enum NoteImportStatus: string
{
    case Pending             = 'PENDING';
    case Processing          = 'PROCESSING';
    case Completed           = 'COMPLETED';
    case CompletedWithErrors = 'COMPLETED_WITH_ERRORS';
    case Failed              = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Pending             => 'En attente',
            self::Processing          => 'En cours de traitement',
            self::Completed           => 'Terminé',
            self::CompletedWithErrors => 'Terminé avec erreurs',
            self::Failed              => 'Échoué',
        };
    }

    /**
     * L'import est terminé (peu importe le résultat) : plus rien à attendre
     * côté client qui poll GET /admin/students/notes-import/{id}.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::CompletedWithErrors, self::Failed], true);
    }
}
