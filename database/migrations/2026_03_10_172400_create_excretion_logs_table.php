<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excretion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                              // ExcretionType enum
            $table->string('size')->nullable();                  // ExcretionSize enum
            $table->unsignedTinyInteger('consistency')->nullable(); // BristolScale 1-7, null for pee
            $table->string('colour')->nullable();
            $table->boolean('has_blood')->default(false);
            $table->string('blood_amount')->default('none');     // BloodAmount enum
            $table->unsignedTinyInteger('urgency')->nullable();  // 1-5
            $table->unsignedTinyInteger('pain_level')->nullable(); // 0-10
            $table->timestamp('logged_at');
            $table->text('notes')->nullable();                   // encrypted
            $table->timestamps();
            $table->index(['user_id', 'logged_at']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excretion_logs');
    }
};
