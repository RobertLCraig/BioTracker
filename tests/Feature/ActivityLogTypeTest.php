<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ActivityType;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_log_list_carries_system_and_own_types_but_not_another_users(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $me = User::factory()->create();
        $food = ActivityType::withoutGlobalScopes()->where('slug', 'food')->sole();
        $mine = ActivityType::withoutGlobalScopes()->create(['name' => 'Yoga', 'slug' => 'yoga', 'user_id' => $me->id]);
        $theirs = ActivityType::withoutGlobalScopes()->create(['name' => 'Secret', 'slug' => 'secret', 'user_id' => User::factory()->create()->id]);

        foreach ([[$food, '-1 hour'], [$mine, '-2 hours'], [$theirs, '-3 hours']] as [$type, $ago]) {
            ActivityLog::withoutGlobalScopes()->create([
                'user_id' => $me->id, 'activity_type_id' => $type->id, 'logged_at' => now()->modify($ago),
            ]);
        }

        $types = $this->actingAs($me, 'sanctum')
            ->getJson('/api/v1/activity-logs')
            ->assertOk()
            ->json('data.*.activity_type.slug');

        $this->assertSame(['food', 'yoga', null], $types);
    }
}
