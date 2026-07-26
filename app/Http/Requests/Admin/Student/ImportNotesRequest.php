<?php

namespace App\Http\Requests\Admin\Student;

use Illuminate\Foundation\Http\FormRequest;

class ImportNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Le fichier lui-même n'est PAS lu ici : la validation ligne par
            // ligne (matricule, subject_id, type, value) est faite par
            // NoteImportService dans le worker, pour ne pas bloquer la
            // requête HTTP le temps de parser tout le CSV.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10 Mo max

            // Optionnel : si omis, on prend automatiquement l'année scolaire
            // la plus récente (cf. NoteController::store).
            'school_year_id' => ['sometimes', 'integer', 'exists:school_years,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Le fichier CSV est obligatoire.',
            'file.mimes'    => 'Le fichier doit être au format CSV.',
            'file.max'      => 'Le fichier ne doit pas dépasser 10 Mo.',
        ];
    }
}
