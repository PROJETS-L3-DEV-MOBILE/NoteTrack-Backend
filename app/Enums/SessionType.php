<?php

namespace App\Enums;

/**
 * Onglets "Tout / Normale / Rattrapage" de GET /student/results.
 * Normale correspond aux notes TEST/EXAM, Rattrapage à
 * MAKEUP .
 */
enum SessionType: string
{
    case Normale = 'NORMALE';
    case Rattrapage = 'RATTRAPAGE';
}
