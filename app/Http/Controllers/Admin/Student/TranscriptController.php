<?php

namespace App\Http\Controllers\Admin\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Student\TranscriptRequest;
use App\Models\Student;
use App\Services\TranscriptService;
use Illuminate\Http\Response;

class TranscriptController extends Controller
{
    public function __construct(protected TranscriptService $transcriptService) {}

    /**
     * GET /admin/students/{student}/transcript
     *
     * Génère et renvoie le relevé de notes PDF d'un étudiant.
     *
     * Reste volontairement synchrone (contrairement à l'import CSV) : il ne
     * s'agit ici que d'un rendu HTML -> PDF pour un seul étudiant, une
     * opération de l'ordre de quelques centaines de millisecondes qui ne
     * justifie pas de passer par la queue.
     *
     * Query params optionnels :
     *   - school_year_id : ignoré pour l'instant côté calcul (les notes sont
     *     déjà scoping par la promotion/classe active de l'étudiant) mais
     *     accepté pour un usage futur multi-années.
     *   - semester_id    : limite le relevé à un semestre (bulletin semestriel).
     *   - download=true  : force le téléchargement (Content-Disposition: attachment).
     */
    public function show(TranscriptRequest $request, Student $student): Response
    {
        $validated = $request->validated();

        $data = $this->transcriptService->build(
            student: $student,
            schoolYearId: $validated['school_year_id'] ?? null,
            semesterId: $validated['semester_id'] ?? null,
        );

        $pdf = $this->transcriptService->render($data);

        $filename = "releve-notes-{$student->matricule}.pdf";

        return ($validated['download'] ?? false)
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
