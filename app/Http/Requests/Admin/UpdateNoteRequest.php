<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // RG01 : note entre 0 et 20, incrément de 0,25
            'value'  => ['sometimes', 'nullable', 'numeric', 'between:0,20', 'multiple_of:0.25'],
            'status' => ['sometimes', Rule::in(['presente', 'abs_justifiee', 'abs_injustifiee'])],
        ];
    }
}
