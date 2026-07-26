<?php

namespace App\Http\Requests\Admin\Student;

use Illuminate\Foundation\Http\FormRequest;

class TranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Défaut : année scolaire la plus récente si omis.
            'school_year_id' => ['sometimes', 'integer', 'exists:school_years,id'],

            // Défaut : les 2 semestres (relevé annuel complet) si omis.
            'semester_id' => ['sometimes', 'integer', 'exists:semesters,id'],

            // true => Content-Disposition: attachment (téléchargement forcé)
            // false/absent => inline (affichage direct dans le navigateur)
            'download' => ['sometimes', 'boolean'],
        ];
    }
}
