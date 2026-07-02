<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ue_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ue_id')->constrained('ues')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['ue_id', 'session_id']); // une UE ne peut être dans une session qu'une fois
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ue_sessions');
    }
};