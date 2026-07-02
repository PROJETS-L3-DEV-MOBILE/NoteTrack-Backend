<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'session_id' => ['required', 'exists:exam_sessions,id'],
            // RG01 : note entre 0 et 20, incrément de 0,25
            'value'      => ['nullable', 'numeric', 'between:0,20', 'multiple_of:0.25'],
            'status'     => ['required', Rule::in(['presente', 'abs_justifiee', 'abs_injustifiee'])],
        ];
    }
}
