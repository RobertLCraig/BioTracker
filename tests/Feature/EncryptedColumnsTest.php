<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\SymptomLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Only proves anything on a database that enforces varchar length (run the suite on Postgres). */
class EncryptedColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_encrypted_names_fit(): void
    {
        $this->actingAs($user = User::factory()->create());
        $long = str_repeat('Example tablet (brand name or generic name) ', 3);

        $med = Medication::create(['name' => $long, 'prescribed_by' => $long, 'is_active' => true]);
        $symptom = SymptomLog::create(['symptom' => $long, 'severity' => 3, 'logged_at' => now()]);

        $this->assertSame($long, $med->fresh()->name);
        $this->assertSame($long, $med->fresh()->prescribed_by);
        $this->assertSame($long, $symptom->fresh()->symptom);
    }
}
