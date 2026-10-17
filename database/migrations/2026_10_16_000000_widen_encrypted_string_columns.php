<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These columns hold Crypt ciphertext, which is about five times the plain text, so a 255-char
 * limit rejects a name of about 45 characters. SQLite never enforced the limit; Postgres does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->text('name')->change();
            $table->text('prescribed_by')->nullable()->change();
        });
        Schema::table('symptom_logs', function (Blueprint $table) {
            $table->text('symptom')->change();
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('prescribed_by')->nullable()->change();
        });
        Schema::table('symptom_logs', function (Blueprint $table) {
            $table->string('symptom')->change();
        });
    }
};
