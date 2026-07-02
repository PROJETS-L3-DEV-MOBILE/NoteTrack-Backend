<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renommée "exam_sessions" (au lieu de "sessions") pour ne pas entrer
        // en collision avec la table technique "sessions" du driver de session
        // Laravel, créée dans 0001_01_01_000000_create_users_table.php.
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label');
            $table->integer('year');
            $table->enum('status', ['SAISIE', 'PUBLIEE', 'VERROUILLEE'])->default('SAISIE'); // RG08
            $table->enum('type', ['normale', 'rattrapage']);
            $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
    }
};
