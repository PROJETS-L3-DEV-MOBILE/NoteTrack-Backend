<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Note, Student, NoteHistory, Subject};
use App\Enums\{NoteType, NoteStatus};
use App\Http\Requests\NoteRequest;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class NoteController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /notes/subject/{subject_id}
     * Liste complète des étudiants avec l'état de leurs notes imbriquées pour une matière.
     */
    public function indexBySubject(Request $request, string $subjectId): JsonResponse
    {
        $this->authorize('viewBySubject', [Note::class, $subjectId]);

        $schoolYearId = $request->query('school_year_id');

        $subject = Subject::findOrFail($subjectId);

        $students = Student::with(['notes' => function ($query) use ($subjectId, $schoolYearId) {
            $query->where('subject_id', $subjectId)
                ->when($schoolYearId !== null, fn($q) => $q->where('school_year_id', $schoolYearId));
        }])->get();

        $notes = $students->map(fn($student) => [
            'student' => [
                'id'        => $student->id,
                'matricule' => $student->matricule,
                'full_name' => "{$student->first_name} {$student->last_name}",
            ],
            'notes' => collect(NoteType::cases())->mapWithKeys(function ($type) use ($student) {
                $note = $student->notes->firstWhere('type', $type);

                if ($note === null) return [];

                return [strtolower($type->value) => [
                    'id'          => $note->id,
                    'value'       => (float) $note->value,
                    'status'      => $note->status->value,
                    'school_year' => $note->schoolYear
                ]];
            })->toArray()
        ]);

        return response()->json(
            ["subject" => $subject, "data" => $notes],
            200
        );
    }

    /**
     * POST /notes/subject/{subject_id}
     * Saisie standard d'une note ou d'une absence (value: -1).
     */
    public function store(NoteRequest $request, string $subjectId): JsonResponse
    {
        $this->authorize('update', new Note(['subject_id' => $subjectId]));

        $validated = $request->validated();

        $data = array_merge($validated, [
            'id'             => (string) Str::uuid(),
            'subject_id'     => $subjectId,
            'status'         => NoteStatus::Pending,
            'created_by'     => $request->user()->id,
        ]);

        $note = Note::create($data);

        return response()->json($note, 201);
    }

    /**
     * GET /notes/{note_id}
     */
    public function show(Note $note): JsonResponse
    {
        $this->authorize('view', $note);

        return response()->json($note->load(['student', 'subject', 'histories']), 200);
    }

    /**
     * PUT/PATCH /notes/{note_id}
     * Modification manuelle avec historisation.
     */
    public function update(NoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        if ($note->status === NoteStatus::Locked) {
            return response()->json(['message' => 'Can not modify a locked note.'], 423);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($note, $request, $validated) {
            $history = new NoteHistory();
            $history->fill([
                'id'         => Str::uuid(),
                'note_id'    => $note->id,
                'old_value'  => $note->value,
                'new_value'  => $request->value,
                'changed_by' => $request->user()->id,
                'school_year_id' => $request->school_year_id,
                'changed_at' => now()
            ]);
            $history->save();

            $note->update($validated);
        });

        return response()->json(
            $note->load(['histories' => fn($query) => $query->orderBy('changed_at', 'desc')]),
            200
        );
    }

    /**
     * PATCH /notes/{note_id}/publish
     */
    public function publish(Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        if ($note->status !== NoteStatus::Pending) {
            return response()->json(['message' => 'Only pending notes can be published.'], 422);
        }

        $note->fill([
            'status'       => NoteStatus::Published,
            'published_at' => now()
        ]);
        $note->save();

        return response()->json($note, 200);
    }

    /**
     * PATCH /notes/subject/{subject_id}/publish
     * Bulk — Publication en masse de toutes les notes PENDING de la matière.
     */
    public function bulkPublish(string $subjectId): JsonResponse
    {
        $this->authorize('update', new Note(['subject_id' => $subjectId]));

        Note::where('subject_id', $subjectId)
            ->where('status', NoteStatus::Pending)
            ->update(['status' => NoteStatus::Published, 'published_at' => now()]);

        return response()->json(['message' => 'All pending notes have been published.'], 200);
    }

    /**
     * PATCH /notes/{note_id}/lock
     */
    public function lock(Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        if ($note->status !== NoteStatus::Published) {
            return response()->json(['message' => 'Only published notes can be locked.'], 422);
        }

        $note->fill(['status' => NoteStatus::Locked]);
        $note->save();

        return response()->json($note, 200);
    }

    /**
     * PATCH /notes/subject/{subject_id}/lock
     * Bulk — Verrouillage en masse de toutes les notes PUBLISHED de la matière.
     */
    public function bulkLock(string $subjectId): JsonResponse
    {

        $this->authorize('update', new Note(['subject_id' => $subjectId]));

        Note::where('subject_id', $subjectId)
            ->where('status', NoteStatus::Published)
            ->update(['status' => NoteStatus::Locked]);

        return response()->json(['message' => 'All published notes have been locked.'], 200);
    }

    /**
     * DELETE /notes/{note_id}
     */
    public function destroy(Note $note): JsonResponse
    {
        $this->authorize('delete', $note);

        if ($note->status === NoteStatus::Locked) {
            return response()->json(['message' => 'Can not delete a locked note.'], 423);
        }

        // 1. Cannot delete Test if exam of makeup exists
        if ($note->type === NoteType::Test) {
            $hasDependents = Note::where('student_id', $note->student_id)
                ->where('subject_id', $note->subject_id)
                ->whereIn('type', [NoteType::Exam, NoteType::Makeup])
                ->exists();

            if ($hasDependents) {
                return response()->json([
                    'message' => 'Cannot delete test because an exam or makeup already exists.'
                ], 422);
            }
        }

        // 2. Cannot delete Exam if Makeup exists
        if ($note->type === NoteType::Exam) {
            $hasMakeup = Note::where('student_id', $note->student_id)
                ->where('subject_id', $note->subject_id)
                ->where('type', NoteType::Makeup)
                ->exists();

            if ($hasMakeup) {
                return response()->json([
                    'message' => 'Cannot delete exam because a makeup already exists.'
                ], 422);
            }
        }

        // Suppression de la note
        $note->delete();

        return response()->json(['message' => 'Note deleted successfully.'], 200);
    }
}
