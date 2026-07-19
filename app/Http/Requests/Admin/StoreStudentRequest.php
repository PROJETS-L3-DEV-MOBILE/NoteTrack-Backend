<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'matricule'  => ['required', 'string', 'unique:students,matricule'],
            'classe_id'  => ['required', 'uuid', 'exists:classes,id'],
            'prom_id'    => ['required', 'uuid', 'exists:promotions,id'],
            'number'     => ['nullable', 'string'],
        ];
    }
}
