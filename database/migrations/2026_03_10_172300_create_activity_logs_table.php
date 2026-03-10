<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->cascadeOnDelete();
            $table->timestamp('logged_at');
            $table->integer('duration_minutes')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit')->nullable();
            $table->integer('calories')->nullable();
            $table->text('notes')->nullable(); // encrypted
            $table->json('metadata')->nullable(); // flexible k/v
            $table->timestamps();
            $table->index(['user_id', 'logged_at']);
            $table->index(['user_id', 'activity_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
