<?php

namespace App\Http\Controllers\Admin\Student;

use App\Enums\NoteImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Student\ImportNotesRequest;
use App\Http\Resources\Admin\Student\NoteImportResource;
use App\Jobs\ProcessNoteImportJob;
use App\Models\NoteImport;
use App\Models\SchoolYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NoteImportController extends Controller
{
    /**
     * POST /admin/students/notes-import
     *
     * Reçoit le CSV, le stocke sur le disque, crée un enregistrement de
     * suivi (PENDING) puis dispatche ProcessNoteImportJob sur la queue.
     *
     * Ne lit jamais le contenu du CSV ici : c'est tout l'intérêt du
     * traitement en arrière-plan — l'admin peut uploader un fichier de
     * plusieurs milliers de lignes sans jamais bloquer l'API le temps du
     * parsing. La réponse 202 revient en quelques millisecondes.
     */
    public function store(ImportNotesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $schoolYearId = $validated['school_year_id']
            ?? SchoolYear::query()->latest('id')->value('id');

        $file = $request->file('file');
        $storedPath = $file->storeAs(
            'imports/notes',
            Str::uuid() . '.csv',
            'local'
        );

        $import = NoteImport::create([
            'id'                 => (string) Str::uuid(),
            'original_filename'  => $file->getClientOriginalName(),
            'file_path'          => $storedPath,
            'status'             => NoteImportStatus::Pending,
            'school_year_id'     => $schoolYearId,
            'created_by'         => $request->user()->id,
        ]);

        ProcessNoteImportJob::dispatch($import->id);

        return response()->json([
            'message' => "Fichier reçu. L'import a été mis en file d'attente et se déroule en arrière-plan.",
            'import'  => new NoteImportResource($import),
        ], 202);
    }

    /**
     * GET /admin/students/notes-import
     * Historique des imports (le plus récent en premier), pour un tableau de suivi côté front.
     */
    public function index(Request $request): JsonResponse
    {
        $imports = NoteImport::with(['createdBy', 'schoolYear'])
            ->where('created_by', $request->user()->id)
            ->latest('created_at')
            ->paginate($request->integer('limit', 15));

        return response()->json([
            'total'   => $imports->total(),
            'imports' => NoteImportResource::collection($imports->items()),
        ], 200);
    }

    /**
     * GET /admin/students/notes-import/{noteImport}
     * Endpoint de polling côté front pour suivre la progression en temps quasi réel.
     */
    public function show(NoteImport $noteImport): JsonResponse
    {
        $noteImport->load(['createdBy', 'schoolYear']);

        return response()->json(new NoteImportResource($noteImport), 200);
    }
}
