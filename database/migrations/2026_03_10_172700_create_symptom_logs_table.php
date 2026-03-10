<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symptom_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symptom');                       // encrypted
            $table->unsignedTinyInteger('severity');         // 1-10
            $table->string('body_area')->nullable();
            $table->timestamp('logged_at');
            $table->integer('duration_minutes')->nullable();
            $table->text('notes')->nullable();               // encrypted
            $table->timestamps();
            $table->index(['user_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptom_logs');
    }
};
