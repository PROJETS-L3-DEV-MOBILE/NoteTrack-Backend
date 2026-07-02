<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();  // ex: GIFL23IPOO
            $table->string('label');
            $table->integer('credits');
            $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ues');
    }
};