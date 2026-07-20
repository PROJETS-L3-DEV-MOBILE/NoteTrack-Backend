<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('note_id')->constrained('notes')->cascadeOnDelete();
            $table->decimal('old_value', 4, 2)->nullable();
            $table->decimal('new_value', 4, 2)->nullable();
            $table->foreignUuid('changed_by')->constrained('users')->cascadeOnDelete(); // RG05
            $table->foreignId('school_year_id')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_histories');
    }
};
