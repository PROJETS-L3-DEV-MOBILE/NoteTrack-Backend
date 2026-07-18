<?php

namespace App\Enums;

enum NoteStatus: string
{
    case Pending = 'PENDING';
    case Published = 'PUBLISHED';
    case Locked = 'LOCKED';
}
