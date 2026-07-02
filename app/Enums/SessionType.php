<?php

namespace App\Enums;

enum SessionType: string
{
    case Normale     = 'normale';
    case Rattrapage  = 'rattrapage';

    public function label(): string
    {
        return match ($this) {
            self::Normale    => 'Session normale',
            self::Rattrapage => 'Session de rattrapage',
        };
    }
}