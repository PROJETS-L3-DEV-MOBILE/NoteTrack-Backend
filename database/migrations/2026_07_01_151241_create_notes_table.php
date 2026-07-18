<?php

use App\Enums\NoteStatus;
use App\Enums\NoteType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('value', 4, 2)->nullable(); // nullable : absence justifiée (RG10), -1 si non justifiée !!
            $table->enum('status', array_column(NoteStatus::cases(), 'value'))->default(NoteStatus::Pending->value);
            $table->enum('type', array_column(NoteType::cases(), 'value'));
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'type']); // une seule note par étudiant/matière/type
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
