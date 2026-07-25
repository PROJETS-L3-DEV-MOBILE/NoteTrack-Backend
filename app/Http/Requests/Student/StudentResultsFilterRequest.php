<?php

namespace App\Http\Requests\Student;

use App\Enums\SessionType;
use Illuminate\Validation\Rules\Enum;

/**
 * GET /student/results — mêmes filtres que StudentFilterRequest, plus
 * `session` (onglets Tout / Normale / Rattrapage ; absent = Tout).
 */
class StudentResultsFilterRequest extends StudentFilterRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'session' => ['sometimes', new Enum(SessionType::class)],
        ];
    }
}
