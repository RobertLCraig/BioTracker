<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                          // VitalType enum
            $table->decimal('value', 10, 2);
            $table->string('secondary_value')->nullable();   // e.g. diastolic for BP
            $table->string('unit');
            $table->timestamp('logged_at');
            $table->string('source')->default('manual');     // manual | fitbit | apple_health | health_connect
            $table->text('notes')->nullable();               // encrypted
            $table->timestamps();
            $table->index(['user_id', 'type', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vital_logs');
    }
};
