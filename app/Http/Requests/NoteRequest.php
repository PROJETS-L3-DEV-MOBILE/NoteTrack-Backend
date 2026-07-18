<?php

namespace App\Http\Requests;

use App\Enums\{NoteType, NoteStatus};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'student_id' => 'required|uuid|exists:students,id',
                'type'       => ['required', new Enum(NoteType::class)],
                'value'      => 'required|numeric|between:-1,20',
                'status'     => ['nullable', new Enum(NoteStatus::class)],
            ];
        }

        return [
            'value'  => 'required|numeric|between:-1,20',
            'status' => ['nullable', new Enum(NoteStatus::class)],
            'type'   => ['nullable', new Enum(NoteType::class)],
        ];
    }
}
