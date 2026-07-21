<?php

namespace App\Http\Requests\Admin\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class ResultsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Libellé de la classe (Classes::label), ex. "L1", "L2"...
            'level'       => ['nullable', 'string'],
            'classe_id'    => ['nullable', 'uuid', 'exists:classes,id'],
            'school_year' => ['nullable', 'string'],
        ];
    }
}
