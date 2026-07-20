<?php

namespace App\Http\Requests;

use App\Enums\{NoteType, NoteStatus};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Models\Subject;
use App\Models\Note;

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
                'school_year_id' => ['required', 'int', 'exists:school_years,id']
            ];
        }

        return [
            'value'  => 'required|numeric|between:-1,20',
            'status' => ['nullable', new Enum(NoteStatus::class)],
            'type'   => ['nullable', new Enum(NoteType::class)],
            'school_year_id' => ['nullable', 'int', 'exists:school_years,id']
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // if any basic validation failed
            if ($validator->errors()->any()) return;

            $note = $this->route('note');
            $typeInput = $this->input('type');

            $targetType = $typeInput ? NoteType::from($typeInput) : ($note ? $note->type : null);

            // update without changing type
            if (!$targetType) return;

            $subjectId = $note ? $note->subject_id : ($this->route('subject_id') ?? $this->input('subject_id'));
            $studentId = $note ? $note->student_id : $this->input('student_id');

            // get all notes
            $query = Note::where('student_id', $studentId)->where('subject_id', $subjectId);
            if ($note) {
                $query->where('id', '!=', $note->id);
            }

            $notes = $query->get()->keyBy(fn($n) => $n->type->value);

            // verify duplicate
            if ($notes->has($targetType->value)) {
                $validator->errors()->add('type', 'A note or absence already exists for this subject and type.');
                return;
            }

            // exam needs test first
            if ($targetType === NoteType::Exam && !$notes->has(NoteType::Test->value)) {
                $validator->errors()->add('type', 'Student need test before exam.');
            }

            // makeup needs test and exam failed first
            if ($targetType === NoteType::Makeup) {
                if (!$notes->has(NoteType::Test->value) || !$notes->has(NoteType::Exam->value)) {
                    $validator->errors()->add('type', 'Makeup need test and exam first.');
                    return;
                }

                $total = max(0, (float) $notes->get(NoteType::Test->value)->value)
                    + max(0, (float) $notes->get(NoteType::Exam->value)->value);

                $subject = Subject::find($subjectId);
                if ($total > $subject?->threshold) {
                    $validator->errors()->add('type', 'Student has already validated the subject.');
                }
            }
        });
    }
}
