<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_year_id' => ['sometimes', 'integer', 'exists:school_years,id'],
            'class_id'    => ['sometimes', 'uuid', 'exists:classes,id'],
            'semester_id' => ['sometimes', 'integer', 'exists:semesters,id'],
        ];
    }
}
