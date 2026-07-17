<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — one lab order / collection event.
 *
 * Inferred, not entered directly: upserted from the lab_order_id + collected_at
 * + source carried by the incoming result rows. Results sharing an order id
 * attach to the same panel; a lone manual result has no panel (lab_panel_id null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_panels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();            // panel name if known (U&E, FBC …)
            $table->string('lab_order_id')->nullable();    // PKB labOrderId — panel grouping key
            $table->timestamp('collected_at')->nullable(); // sample date — used for trending
            $table->timestamp('reported_at')->nullable();  // when the lab released it
            $table->string('source')->default('manual');   // manual | pkb_paste | pkb_json | file_import
            $table->string('performing_org')->nullable();  // PKB source.displayText
            $table->string('source_via')->nullable();      // PKB source.via
            $table->string('lab_type')->nullable();        // PKB labType, e.g. BLS
            $table->text('notes')->nullable();             // encrypted
            $table->string('client_id')->nullable();       // dedup key (see importer)
            $table->timestamps();

            $table->index(['user_id', 'collected_at']);
            $table->unique(['user_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_panels');
    }
};
