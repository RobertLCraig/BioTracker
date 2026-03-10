<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');               // encrypted
            $table->string('dosage')->nullable();
            $table->string('unit')->nullable();
            $table->string('frequency')->nullable();
            $table->string('prescribed_by')->nullable(); // encrypted
            $table->text('notes')->nullable();    // encrypted
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
