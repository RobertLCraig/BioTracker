<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkey_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Passkey');          // user-given friendly name
            $table->string('credential_id');                    // base64url-encoded raw id
            $table->json('credential_source');                  // full PublicKeyCredentialSource JSON
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique('credential_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkey_credentials');
    }
};
