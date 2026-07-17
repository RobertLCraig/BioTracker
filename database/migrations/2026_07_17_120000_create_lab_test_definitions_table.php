<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — Lab / Test Results.
 *
 * Seeded analyte catalog (shared reference data, NOT user-owned). Lab results
 * link to a definition so the same analyte trends consistently across labs and
 * naming variants. Matching order: PKB testResultTypeId first, then slug/aliases.
 * Unmatched analytes auto-create an uncurated row (non-lossy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_test_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('pkb_type_id')->nullable()->unique(); // PKB testResultTypeId
            $table->string('code_system')->nullable();           // e.g. loincMapping
            $table->string('code')->nullable();                  // LOINC / SNOMED
            $table->string('name');                              // canonical name
            $table->string('slug')->unique();                    // fallback match key
            $table->json('aliases')->nullable();                 // naming variants
            $table->string('category')->nullable();              // Biochemistry, Haematology …
            $table->string('default_unit')->nullable();
            $table->decimal('default_range_low', 12, 4)->nullable();
            $table->decimal('default_range_high', 12, 4)->nullable();
            $table->boolean('is_curated')->default(false);       // seeded/reviewed vs auto-created
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_test_definitions');
    }
};
