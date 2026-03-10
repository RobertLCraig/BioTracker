<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable client_id to log tables that don't have metadata JSON.
 * Used for idempotent batch imports from mobile clients.
 * (activity_logs stores client_id in its existing metadata JSON column.)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['excretion_logs', 'medication_logs', 'symptom_logs', 'vital_logs'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('client_id')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['excretion_logs', 'medication_logs', 'symptom_logs', 'vital_logs'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('client_id');
            });
        }
    }
};
