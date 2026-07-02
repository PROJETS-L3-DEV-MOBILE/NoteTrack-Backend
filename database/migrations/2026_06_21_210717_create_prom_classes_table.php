<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prom_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prom_id')->constrained('promotions')->onDelete('cascade');
            $table->foreignUuid('class_id')->constrained('classes')->onDelete('cascade');
            $table->enum('type', ['S1', 'S2']);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prom_classes');
    }
};