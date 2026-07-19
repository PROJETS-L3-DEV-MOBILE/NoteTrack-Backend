<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name'  => ['nullable', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'unique:users,email'],
            'matricule'  => ['nullable', 'string', 'unique:students,matricule'],
            'classe_id'  => ['nullable', 'uuid', 'exists:classes,id'],
            'prom_id'    => ['nullable', 'uuid', 'exists:promotions,id'],
            'number'     => ['nullable', 'string'],
            'is_active'  => ['nullable', 'boolean']
        ];
    }
}
