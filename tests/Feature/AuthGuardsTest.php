<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function signUp()
    {
        return $this->postJson('/api/v1/register', [
            'name' => 'Test Person', 'email' => 'test@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'privacy_consent' => true, 'terms_accepted' => true,
        ]);
    }

    public function test_sign_up_is_refused_when_registration_is_closed(): void
    {
        config(['auth.registration' => false]);

        $this->signUp()->assertForbidden();
        $this->assertSame(0, User::count());
    }

    public function test_sign_up_works_when_registration_is_open(): void
    {
        $this->signUp()->assertCreated();
    }

    public function test_login_is_throttled_after_ten_tries_a_minute(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();
    }
}
