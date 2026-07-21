<?php

namespace App\Enums;

enum TeacherSortEnum: string
{
    case NameAZ = 'name_A_Z';
    case NameZA = 'name_Z_A';
    case CreationDate = 'creation_date';
}
