<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('points_reward')->default(5);
            $table->boolean('is_recurring')->default(false);
            $table->string('frequency')->nullable(); // TaskFrequency enum
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_task_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_task_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_task_completions');
        Schema::dropIfExists('user_tasks');
    }
};
