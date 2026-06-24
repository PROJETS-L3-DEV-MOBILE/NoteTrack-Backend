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
            'number'     => ['nullable', 'string'],
            'prom_id'    => ['required', 'exists:promotions,id'],
        ];
    }
}