<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('value', 4, 2)->nullable(); // nullable : absence justifiée (RG10)
            $table->enum('status', ['presente', 'abs_justifiee', 'abs_injustifiee'])->default('presente'); // RG10
            $table->boolean('is_published')->default(false); // RG04
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'session_id']); // une seule note par étudiant/matière/session
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
