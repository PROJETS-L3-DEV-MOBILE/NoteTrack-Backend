<?php

namespace App\Http\Requests\Admin\Subjects;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUERequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'     => ['nullable', 'string', 'min:1', 'max:255'],
            'color'    => ['nullable', 'string', 'min:1', 'max:255'],
        ];
    }
}
