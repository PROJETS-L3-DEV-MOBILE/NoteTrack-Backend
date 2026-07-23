<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Note, Student, NoteHistory, SchoolYear, Subject};
use App\Enums\{NoteType, NoteStatus};
use App\Http\Requests\NoteRequest;
use App\Services\NotificationService;
use Illuminate\Http\{JsonResponse, Request, Request as HttpRequest};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class NoteController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected NotificationService $notificationService) {}

    /**
     * GET /notes/subject/{subject_id}
     * Liste complète des étudiants avec l'état de leurs notes imbriquées pour une matière.
     */
    public function indexBySubject(Request $request, string $subjectId): JsonResponse
    {
        $subject = Subject::findOrFail($subjectId);

        $this->authorize('viewBySubject', [Note::class, $subject]);

        $schoolYearId = $request->query('school_year_id');

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
        $subject = Subject::findOrFail($subjectId);

        $this->authorize('create', [Note::class, $subject]);

        $validated = $request->validated();
        $schoolYearId = SchoolYear::latest('id')->firstOrFail()->id;

        $data = array_merge($validated, [
            'id'             => (string) Str::uuid(),
            'school_year_id' => $schoolYearId,
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
     * Modification manuelle avec historisation conditionnelle.
     */
    public function update(NoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        if ($note->status === NoteStatus::Locked) {
            return response()->json(['message' => 'Cannot modify a locked note.'], 423);
        }

        $validated = $request->validated();
        $note->fill($validated);

        if ($note->isDirty()) {
            DB::transaction(function () use ($note, $request) {
                if ($note->isDirty('value')) {
                    NoteHistory::create([
                        'id'             => (string) Str::uuid(),
                        'note_id'        => $note->id,
                        'old_value'      => $note->getOriginal('value'),
                        'new_value'      => $note->value,
                        'changed_by'     => $request->user()->id,
                        'school_year_id' => $note->school_year_id,
                        'changed_at'     => now(),
                    ]);
                }

                $note->save();
            });
        }

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
    public function bulkPublish(Request $request, string $subjectId,): JsonResponse
    {
        $subject = Subject::findOrFail($subjectId);

        $this->authorize('manageNotes', [Note::class, $subject]);

        $targetStudents = $subject->getUsersWithPendingNotes();

        if ($targetStudents->isEmpty()) {
            return response()->json(['message' => 'No pending notes found to publish.'], 200);
        }

        $publishedCount = Note::where('subject_id', $subject->id)
            ->where('status', NoteStatus::Pending)
            ->update([
                'status' => NoteStatus::Published,
                'published_at' => now(),
            ]);

        $this->notificationService->notifyNotesPublished(
            actor: $request->user(),
            subject: $subject,
            count: $publishedCount,
            targetStudents: $targetStudents
        );

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
    public function bulkLock(Request $request, string $subjectId,): JsonResponse
    {
        $subject = Subject::findOrFail($subjectId);

        $this->authorize('manageNotes', [Note::class, $subject]);

        $targetStudents = $subject->getUsersWithPublishedNotes();

        $lockedCount = Note::where('subject_id', $subjectId)
            ->where('status', NoteStatus::Published)
            ->update(['status' => NoteStatus::Locked]);

        if ($lockedCount === 0) {
            return response()->json(['message' => 'No published notes found to lock.'], 200);
        }

        $this->notificationService->notifyNotesLocked(
            actor: $request->user(),
            subject: $subject,
            count: $lockedCount,
            targetStudents: $targetStudents
        );

        return response()->json(['message' => 'All published notes have been locked.'], 200);
    }

    /**
     * DELETE /notes/{note_id}
     */
    public function destroy(Note $note): JsonResponse
    {
        // 1. Authorization : Teacher -> class
        $this->authorize('delete', $note);

        // 2. Published / locked
        if ($note->status === NoteStatus::Published) {
            return response()->json(['message' => 'Cannot delete a published note.'], 422);
        }

        if ($note->status === NoteStatus::Locked) {
            return response()->json(['message' => 'Cannot delete a locked note.'], 423);
        }

        // 3. Test : Exam / Makeup already exists
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

        // 4. Exam -> Makeup already exists
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

        $note->delete();

        return response()->json(['message' => 'Note deleted successfully.'], 200);
    }
}
