<?php

namespace App\Services;

use App\Enums\NoteImportStatus;
use App\Enums\NoteStatus;
use App\Enums\NoteType;
use App\Models\Note;
use App\Models\NoteHistory;
use App\Models\NoteImport;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Traite un import CSV de notes.
 *
 * IMPORTANT : cette classe est volontairement conçue pour être appelée
 * uniquement depuis App\Jobs\ProcessNoteImportJob (donc depuis un worker
 * `php artisan queue:work`), jamais depuis le thread HTTP. Le fichier est lu
 * en streaming (fgetcsv ligne par ligne, jamais file_get_contents) pour
 * garder une empreinte mémoire constante même sur un CSV volumineux.
 *
 * Format CSV attendu (en-tête obligatoire, insensible à la casse,
 * séparateur `,` ou `;` auto-détecté) :
 *
 *   matricule,subject_id,type,value
 *   ET-2024-001,3fa85f64-5717-4562-b3fc-2c963f66afa6,TEST,15.5
 *   ET-2024-001,3fa85f64-5717-4562-b3fc-2c963f66afa6,EXAM,12
 *   ET-2024-002,3fa85f64-5717-4562-b3fc-2c963f66afa6,TEST,-1
 *
 *   - matricule  : matricule existant de l'étudiant (students.matricule)
 *   - subject_id : UUID existant de la matière (subjects.id)
 *   - type       : TEST | EXAM | MAKEUP (insensible à la casse)
 *   - value      : -1 (absence justifiée, RG10) ou nombre entre 0 et 20
 *
 * Une colonne optionnelle `school_year_id` peut être ajoutée par ligne pour
 * cibler une année scolaire précise ; sinon on utilise celle choisie lors de
 * l'upload (NoteImport::school_year_id), et à défaut la plus récente.
 *
 * Une ligne (student_id, subject_id, type) qui n'a pas encore de note crée
 * une note PENDING (comme NoteController::store). Si une note existe déjà :
 *   - LOCKED  -> ligne rejetée (erreur), la note verrouillée n'est jamais touchée.
 *   - sinon   -> valeur mise à jour + historisation (comme NoteController::update).
 */
class NoteImportService
{
    private const REQUIRED_COLUMNS = ['matricule', 'subject_id', 'type', 'value'];

    // Sauvegarde la progression toutes les N lignes plutôt qu'à chaque ligne,
    // pour ne pas saturer la base sur un fichier de plusieurs milliers de lignes.
    private const PROGRESS_FLUSH_EVERY = 25;

    // Plafond du nombre d'erreurs conservées dans le rapport JSON.
    private const MAX_STORED_ERRORS = 200;

    public function __construct(protected NotificationService $notificationService) {}

    public function process(string $noteImportId): void
    {
        $import = NoteImport::findOrFail($noteImportId);

        $import->update([
            'status'     => NoteImportStatus::Processing,
            'started_at' => now(),
        ]);

        $absolutePath = Storage::disk('local')->path($import->file_path);

        if (! is_readable($absolutePath)) {
            $this->finish($import, NoteImportStatus::Failed, errors: [[
                'line'    => 0,
                'message' => 'Le fichier importé est introuvable ou illisible sur le disque.',
            ]]);

            return;
        }

        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            $this->finish($import, NoteImportStatus::Failed, errors: [[
                'line'    => 0,
                'message' => "Impossible d'ouvrir le fichier CSV.",
            ]]);

            return;
        }

        try {
            $delimiter = $this->detectDelimiter($absolutePath);

            $header = fgetcsv($handle, 0, $delimiter);

            if ($header === false) {
                $this->finish($import, NoteImportStatus::Failed, errors: [[
                    'line'    => 0,
                    'message' => 'Le fichier CSV est vide.',
                ]]);

                return;
            }

            $columns = $this->normalizeHeader($header);
            $missing = array_diff(self::REQUIRED_COLUMNS, $columns);

            if (! empty($missing)) {
                $this->finish($import, NoteImportStatus::Failed, errors: [[
                    'line'    => 1,
                    'message' => "Colonnes manquantes dans l'en-tête : " . implode(', ', $missing),
                ]]);

                return;
            }

            $import->update(['total_rows' => $this->countDataRows($absolutePath, $delimiter)]);

            $defaultSchoolYearId = $import->school_year_id
                ?? SchoolYear::query()->latest('id')->value('id');

            $line = 1; // ligne 1 = en-tête
            $processed = 0;
            $imported = 0;
            $updated = 0;
            $failed = 0;
            $errors = [];

            // Caches locaux : évite de re-requêter le même étudiant/matière
            // pour chaque ligne d'un CSV où un étudiant a plusieurs lignes
            // (TEST, EXAM, MAKEUP...).
            $studentsCache = [];
            $subjectsCache = [];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;

                if ($this->isBlankRow($row)) {
                    continue;
                }

                $processed++;
                $data = $this->mapRow($columns, $row);

                try {
                    $result = $this->importRow(
                        data: $data,
                        createdBy: $import->created_by,
                        defaultSchoolYearId: $defaultSchoolYearId,
                        studentsCache: $studentsCache,
                        subjectsCache: $subjectsCache,
                    );

                    $result === 'created' ? $imported++ : $updated++;
                } catch (Throwable $e) {
                    $failed++;

                    if (count($errors) < self::MAX_STORED_ERRORS) {
                        $errors[] = [
                            'line'       => $line,
                            'matricule'  => $data['matricule']  ?? null,
                            'subject_id' => $data['subject_id'] ?? null,
                            'type'       => $data['type']       ?? null,
                            'message'    => $e->getMessage(),
                        ];
                    }

                    Log::warning('[NoteImportService] Ligne CSV rejetée', [
                        'note_import_id' => $import->id,
                        'line'           => $line,
                        'error'          => $e->getMessage(),
                    ]);
                }

                if ($processed % self::PROGRESS_FLUSH_EVERY === 0) {
                    $import->update([
                        'processed_rows' => $processed,
                        'imported_count' => $imported,
                        'updated_count'  => $updated,
                        'failed_count'   => $failed,
                    ]);
                }
            }

            $status = match (true) {
                $processed > 0 && $failed === $processed => NoteImportStatus::Failed,
                $failed > 0 => NoteImportStatus::CompletedWithErrors,
                default => NoteImportStatus::Completed,
            };

            $this->finish($import, $status, [
                'processed_rows' => $processed,
                'imported_count' => $imported,
                'updated_count'  => $updated,
                'failed_count'   => $failed,
            ], $errors);
        } finally {
            fclose($handle);

            // Le fichier brut n'a plus d'utilité une fois traité : le
            // résultat (compteurs + erreurs) vit désormais dans note_imports.
            Storage::disk('local')->delete($import->file_path);
        }
    }

    /**
     * Traite une ligne et retourne 'created' ou 'updated'.
     *
     * @param array<string, mixed> $studentsCache
     * @param array<string, mixed> $subjectsCache
     *
     * @throws InvalidArgumentException|RuntimeException si la ligne est invalide
     */
    private function importRow(
        array $data,
        string $createdBy,
        ?int $defaultSchoolYearId,
        array &$studentsCache,
        array &$subjectsCache,
    ): string {
        $matricule = trim((string) ($data['matricule'] ?? ''));
        $subjectId = trim((string) ($data['subject_id'] ?? ''));
        $typeRaw   = strtoupper(trim((string) ($data['type'] ?? '')));
        $valueRaw  = trim((string) ($data['value'] ?? ''));

        $schoolYearId = isset($data['school_year_id']) && trim((string) $data['school_year_id']) !== ''
            ? (int) $data['school_year_id']
            : $defaultSchoolYearId;

        if ($matricule === '') {
            throw new InvalidArgumentException('La colonne matricule est vide.');
        }

        if ($subjectId === '' || ! Str::isUuid($subjectId)) {
            throw new InvalidArgumentException("subject_id est vide ou n'est pas un UUID valide.");
        }

        $type = NoteType::tryFrom($typeRaw);

        if ($type === null) {
            throw new InvalidArgumentException("type invalide '{$typeRaw}' (attendu : TEST, EXAM ou MAKEUP).");
        }

        if ($valueRaw === '' || ! is_numeric($valueRaw)) {
            throw new InvalidArgumentException('value doit être numérique.');
        }

        $value = (float) $valueRaw;

        if (! ($value == -1 || ($value >= 0 && $value <= 20))) {
            throw new InvalidArgumentException('value doit être -1 (absence justifiée) ou compris entre 0 et 20.');
        }

        if ($schoolYearId === null) {
            throw new InvalidArgumentException("Aucune année scolaire disponible : créez d'abord une année scolaire.");
        }

        if (! array_key_exists($matricule, $studentsCache)) {
            $studentsCache[$matricule] = Student::where('matricule', $matricule)->first();
        }

        $student = $studentsCache[$matricule];

        if ($student === null) {
            throw new InvalidArgumentException("Aucun étudiant trouvé pour le matricule '{$matricule}'.");
        }

        if (! array_key_exists($subjectId, $subjectsCache)) {
            $subjectsCache[$subjectId] = Subject::find($subjectId);
        }

        $subject = $subjectsCache[$subjectId];

        if ($subject === null) {
            throw new InvalidArgumentException("Aucune matière trouvée pour subject_id '{$subjectId}'.");
        }

        return DB::transaction(function () use ($student, $subject, $type, $value, $schoolYearId, $createdBy) {
            $note = Note::where('student_id', $student->id)
                ->where('subject_id', $subject->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if ($note === null) {
                Note::create([
                    'id'             => (string) Str::uuid(),
                    'value'          => $value,
                    'status'         => NoteStatus::Pending,
                    'type'           => $type,
                    'student_id'     => $student->id,
                    'subject_id'     => $subject->id,
                    'created_by'     => $createdBy,
                    'school_year_id' => $schoolYearId,
                ]);

                return 'created';
            }

            if ($note->status === NoteStatus::Locked) {
                throw new RuntimeException('Note déjà verrouillée (LOCKED) : import ignoré pour cette ligne.');
            }

            if ((float) $note->value !== $value) {
                NoteHistory::create([
                    'id'             => (string) Str::uuid(),
                    'note_id'        => $note->id,
                    'old_value'      => $note->value,
                    'new_value'      => $value,
                    'changed_by'     => $createdBy,
                    'school_year_id' => $note->school_year_id,
                    'changed_at'     => now(),
                ]);

                $note->value = $value;
                $note->save();
            }

            return 'updated';
        });
    }

    private function finish(NoteImport $import, NoteImportStatus $status, array $counts = [], array $errors = []): void
    {
        $import->update([
            'status'         => $status,
            'processed_rows' => $counts['processed_rows'] ?? $import->processed_rows,
            'imported_count' => $counts['imported_count'] ?? $import->imported_count,
            'updated_count'  => $counts['updated_count']  ?? $import->updated_count,
            'failed_count'   => $counts['failed_count']   ?? $import->failed_count,
            'errors'         => $errors !== [] ? $errors : null,
            'finished_at'    => now(),
        ]);

        $import->loadMissing('createdBy');

        if ($import->createdBy) {
            $this->notificationService->notifyNoteImportFinished($import);
        }
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $firstLine = $handle ? (fgets($handle) ?: '') : '';

        if ($handle) {
            fclose($handle);
        }

        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    /**
     * @return string[]
     */
    private function normalizeHeader(array $header): array
    {
        return array_map(function ($col) {
            $col = preg_replace('/^\xEF\xBB\xBF/', '', (string) $col); // strip BOM UTF-8 éventuel

            return strtolower(trim((string) $col));
        }, $header);
    }

    /**
     * @param string[] $columns
     * @return array<string, mixed>
     */
    private function mapRow(array $columns, array $row): array
    {
        $data = [];

        foreach ($columns as $index => $column) {
            $data[$column] = $row[$index] ?? null;
        }

        return $data;
    }

    private function isBlankRow(mixed $row): bool
    {
        if ($row === false || $row === null) {
            return true;
        }

        if (count($row) === 1 && trim((string) ($row[0] ?? '')) === '') {
            return true;
        }

        return false;
    }

    /**
     * Compte les lignes de données (hors en-tête) pour calculer une
     * progression (%). On relit le fichier une seconde fois en streaming :
     * coût négligeable comparé au traitement (requêtes DB) de chaque ligne,
     * et garde toujours une empreinte mémoire constante.
     */
    private function countDataRows(string $path, string $delimiter): int
    {
        $count = 0;
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return 0;
        }

        fgetcsv($handle, 0, $delimiter); // skip header

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $count++;
        }

        fclose($handle);

        return $count;
    }
}
