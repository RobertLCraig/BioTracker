<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Food',     'slug' => 'food',     'icon' => '🍎', 'points_per_log' => 5,  'is_system' => true],
            ['name' => 'Drink',    'slug' => 'drink',    'icon' => '💧', 'points_per_log' => 5,  'is_system' => true],
            ['name' => 'Exercise', 'slug' => 'exercise', 'icon' => '🏃', 'points_per_log' => 10, 'is_system' => true],
            ['name' => 'Sleep',    'slug' => 'sleep',    'icon' => '😴', 'points_per_log' => 10, 'is_system' => true],
            ['name' => 'Custom',   'slug' => 'custom',   'icon' => '⭐', 'points_per_log' => 5,  'is_system' => true],
        ];

        foreach ($types as $type) {
            ActivityType::withoutGlobalScopes()->updateOrCreate(
                ['slug' => $type['slug'], 'is_system' => true],
                $type
            );
        }
    }
}
