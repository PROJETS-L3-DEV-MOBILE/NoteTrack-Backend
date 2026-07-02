<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Saisie      = 'SAISIE';
    case Publiee     = 'PUBLIEE';
    case Verrouillee = 'VERROUILLEE';

    // Vérifie si la session permet encore la modification de notes.
    // RG05 autorise explicitement la modification après publication (elle est
    // alors tracée) ; seule une session VERROUILLEE (RG08) bloque toute écriture.
    public function isEditable(): bool
    {
        return $this !== self::Verrouillee;
    }
}