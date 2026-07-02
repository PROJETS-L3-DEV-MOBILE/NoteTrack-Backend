<?php

namespace App\Enums;

enum NoteStatus: string
{
    case Presente       = 'presente';
    case AbsJustifiee   = 'abs_justifiee';
    case AbsInjustifiee = 'abs_injustifiee';

    public function label(): string
    {
        return match($this) {
            self::Presente       => 'Présente',
            self::AbsJustifiee   => 'ABS',
            self::AbsInjustifiee => '0 (Absence injustifiée)',
        };
    }
}