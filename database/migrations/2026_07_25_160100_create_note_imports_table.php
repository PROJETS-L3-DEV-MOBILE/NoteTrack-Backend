<?php

use App\Enums\NoteImportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trace l'état d'avancement d'un import CSV de notes traité en tâche de
     * fond (voir App\Jobs\ProcessNoteImportJob). Permet à l'admin de suivre
     * la progression (processed_rows / total_rows) sans bloquer l'API le
     * temps du traitement du fichier.
     */
    public function up(): void
    {
        Schema::create('note_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('original_filename');
            $table->string('file_path');

            $table->enum('status', array_column(NoteImportStatus::cases(), 'value'))
                ->default(NoteImportStatus::Pending->value);

            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            // Liste (plafonnée) des erreurs ligne par ligne : [{line, matricule, subject_id, type, message}]
            $table->json('errors')->nullable();

            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_imports');
    }
};
