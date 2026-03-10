<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('total_calories')->default(0);
            $table->integer('total_water_ml')->default(0);
            $table->integer('exercise_minutes')->default(0);
            $table->decimal('sleep_hours', 4, 2)->default(0);
            $table->integer('log_count')->default(0);
            $table->integer('points_earned')->default(0);
            $table->json('data')->nullable(); // extended breakdown
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
