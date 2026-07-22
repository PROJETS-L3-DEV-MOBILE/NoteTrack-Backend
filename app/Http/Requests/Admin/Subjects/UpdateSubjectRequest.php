<?php

namespace App\Http\Requests\Admin\Subjects;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'min:1', 'max:255'],
            'teacher_id'  => ['sometimes', 'string', 'exists:teachers,id'],
            'semester_id' => ['sometimes', 'string', 'exists:semesters,id'],
            'coefficient' => ['sometimes', 'numeric', 'min:1', 'max:10'],
            'threshold'   => ['sometimes', 'numeric', 'min:0', 'max:20'],
            'credits'     => ['sometimes', 'integer', 'min:1', 'max:60'],
        ];
    }
}
