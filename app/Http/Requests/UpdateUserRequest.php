<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:2', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => "Le nom d'utilisateur est obligatoire.",
            'username.string'   => "Le nom d'utilisateur doit être une chaîne de caractères.",
            'username.min'      => "Le nom d'utilisateur doit contenir au moins 2 caractères.",
            'username.max'      => "Le nom d'utilisateur ne doit pas dépasser 50 caractères.",
        ];
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('username');

        if (is_string($username)) {
            $this->merge(['username' => trim($username)]);
        }
    }
}
