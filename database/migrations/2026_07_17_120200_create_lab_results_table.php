<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — one analyte measurement (the atomic fact a user supplies / imports).
 * Field mapping to the PKB fetchTestHistoryJson payload is documented in
 * docs/lab-results-design.md §2.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_panel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('test_definition_id')->constrained('lab_test_definitions');

            $table->string('external_id')->nullable();     // PKB datapoint id — dedup key
            $table->string('test_name');                   // as reported by the lab
            $table->string('test_code')->nullable();       // PKB testResultTypeId / LOINC
            $table->string('test_key')->index();           // trending/grouping key

            $table->string('value_text');                  // "4.9 mmol/L", "Positive", ">60 mL/min"
            $table->decimal('value_numeric', 12, 4)->nullable();
            $table->string('value_comparator', 3)->nullable(); // < / >
            $table->string('unit')->nullable();

            $table->decimal('range_low', 12, 4)->nullable();
            $table->decimal('range_high', 12, 4)->nullable();
            $table->string('range_text')->nullable();

            $table->string('abnormal_flag')->default('unknown'); // AbnormalFlag (derived)
            $table->string('status')->default('final');          // LabResultStatus
            $table->boolean('textual_only')->default(false);

            $table->timestamp('sampled_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('available_from')->nullable();     // embargoed-until (pending results)
            $table->json('privacy_flags')->nullable();
            $table->text('comment')->nullable();                 // encrypted

            $table->timestamps();

            $table->index(['user_id', 'test_key', 'sampled_at']);
            $table->unique(['user_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_results');
    }
};
