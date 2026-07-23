<?php

namespace App\Http\Requests;

use App\Enums\{NoteType, NoteStatus};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Models\{Subject, Note};
use Illuminate\Validation\Rule;

class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * -1 OR between 0 and 20
     */
    private function noteValueRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'numeric',
            Rule::or([
                Rule::in([-1]),
                'between:0,20',
            ]),
        ];
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->whereNull('deleted_at')],
                'type'       => ['required', new Enum(NoteType::class)],
                'value'      => $this->noteValueRules(required: true),
            ];
        }

        return [
            'value'  => $this->noteValueRules(required: false),
            'status' => ['sometimes', new Enum(NoteStatus::class)],
            'type'   => ['sometimes', new Enum(NoteType::class)],
        ];
    }

    public function withValidator(mixed $validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) return;

            $note = $this->route('note');
            $typeInput = $this->input('type');

            $targetType = $typeInput ? NoteType::from($typeInput) : ($note ? $note->type : null);

            if (!$targetType) return;

            $subjectId = $note ? $note->subject_id : ($this->route('subject_id') ?? $this->input('subject_id'));
            $studentId = $note ? $note->student_id : $this->input('student_id');

            $query = Note::where('student_id', $studentId)->where('subject_id', $subjectId);
            if ($note) {
                $query->where('id', '!=', $note->id);
            }

            $notes = $query->get()->keyBy(fn($n) => $n->type->value);

            // 1. Verify duplicate
            if ($notes->has($targetType->value)) {
                $validator->errors()->add('type', 'A note or absence already exists for this subject and type.');
                return;
            }

            // 2. Exam needs test
            if ($targetType === NoteType::Exam && !$notes->has(NoteType::Test->value)) {
                $validator->errors()->add('type', 'Student needs a test before taking an exam.');
            }

            // 3. Makeup needs test & exam
            if ($targetType === NoteType::Makeup) {
                if (!$notes->has(NoteType::Test->value) || !$notes->has(NoteType::Exam->value)) {
                    $validator->errors()->add('type', 'Makeup requires both a test and an exam first.');
                    return;
                }

                $testVal = max(0, (float) $notes->get(NoteType::Test->value)->value);
                $examVal = max(0, (float) $notes->get(NoteType::Exam->value)->value);

                $average = ($testVal + $examVal) / 2;

                $subject = Subject::find($subjectId);
                if ($subject && $average >= $subject->threshold) {
                    $validator->errors()->add('type', 'Student has already validated the subject.');
                }
            }
        });
    }
}
