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
            'name'     => ['required', 'string', 'min:1', 'max:255'],
            'classe_id' => ['required', 'string', 'exists:classes,id'],
            'color'    => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
