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
            'name'        => ['required', 'string', 'min:1', 'max:255'],
            'teacher_id'  => ['required', 'string', 'exists:teachers,id'],
            'semester_id' => ['required', 'string', 'exists:semesters,id'],
            'coefficient' => ['required', 'numeric', 'min:1', 'max:10'],
            'threshold'   => ['required', 'numeric', 'min:0', 'max:20'],
            'credits'     => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }
}
