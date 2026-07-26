<?php

namespace App\Jobs;

use App\Enums\NoteImportStatus;
use App\Models\NoteImport;
use App\Services\NoteImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dispatché par NoteImportController::store() juste après l'upload.
 * Exécuté par `php artisan queue:work` (QUEUE_CONNECTION=database par
 * défaut dans ce projet, cf. .env.example) : c'est ce qui permet à l'API de
 * répondre immédiatement (202 Accepted) pendant que le CSV, potentiellement
 * volumineux, est parsé et importé en arrière-plan.
 */
class ProcessNoteImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Une seule tentative : en cas d'échec partiel, les erreurs par ligne
     * sont déjà collectées dans note_imports.errors, un retry automatique
     * relancerait tout le fichier depuis le début et pourrait dupliquer des
     * NoteHistory sur les lignes déjà importées avec succès.
     */
    public int $tries = 1;

    /**
     * Le timeout par défaut (60s) est trop court pour un CSV volumineux.
     */
    public int $timeout = 900;

    public function __construct(public string $noteImportId) {}

    public function handle(NoteImportService $service): void
    {
        $service->process($this->noteImportId);
    }

    /**
     * Si le job plante avant même d'atteindre les blocs try/catch internes
     * du service (ex : erreur mémoire, exception non prévue), on marque
     * quand même l'import comme FAILED plutôt que de le laisser bloqué en
     * PROCESSING indéfiniment.
     */
    public function failed(Throwable $exception): void
    {
        NoteImport::where('id', $this->noteImportId)->update([
            'status'      => NoteImportStatus::Failed,
            'errors'      => [[
                'line'    => 0,
                'message' => 'Erreur inattendue pendant le traitement : ' . $exception->getMessage(),
            ]],
            'finished_at' => now(),
        ]);
    }
}
