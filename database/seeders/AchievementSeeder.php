<?php

namespace Database\Seeders;

use App\Enums\AchievementTier;
use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'name'            => 'First Log',
                'description'     => 'Create your very first health log entry.',
                'icon'            => '🌱',
                'condition_type'  => 'total_logs',
                'condition_value' => ['threshold' => 1],
                'points_reward'   => 10,
                'tier'            => AchievementTier::Bronze->value,
            ],
            [
                'name'            => 'Getting Started',
                'description'     => 'Log 10 health entries.',
                'icon'            => '📝',
                'condition_type'  => 'total_logs',
                'condition_value' => ['threshold' => 10],
                'points_reward'   => 20,
                'tier'            => AchievementTier::Bronze->value,
            ],
            [
                'name'            => 'Week Warrior',
                'description'     => 'Maintain a 7-day logging streak.',
                'icon'            => '🔥',
                'condition_type'  => 'streak',
                'condition_value' => ['threshold' => 7],
                'points_reward'   => 25,
                'tier'            => AchievementTier::Bronze->value,
            ],
            [
                'name'            => 'Fortnight Fighter',
                'description'     => 'Maintain a 14-day logging streak.',
                'icon'            => '⚡',
                'condition_type'  => 'streak',
                'condition_value' => ['threshold' => 14],
                'points_reward'   => 50,
                'tier'            => AchievementTier::Silver->value,
            ],
            [
                'name'            => 'Month Master',
                'description'     => 'Maintain a 30-day logging streak.',
                'icon'            => '🏆',
                'condition_type'  => 'streak',
                'condition_value' => ['threshold' => 30],
                'points_reward'   => 100,
                'tier'            => AchievementTier::Silver->value,
            ],
            [
                'name'            => 'Century Club',
                'description'     => 'Log 100 health entries.',
                'icon'            => '💯',
                'condition_type'  => 'total_logs',
                'condition_value' => ['threshold' => 100],
                'points_reward'   => 50,
                'tier'            => AchievementTier::Silver->value,
            ],
            [
                'name'            => 'Photographer',
                'description'     => 'Attach photos to 10 log entries.',
                'icon'            => '📸',
                'condition_type'  => 'photos_attached',
                'condition_value' => ['threshold' => 10],
                'points_reward'   => 20,
                'tier'            => AchievementTier::Bronze->value,
            ],
            [
                'name'            => 'Health Historian',
                'description'     => 'Log 1000 health entries.',
                'icon'            => '📚',
                'condition_type'  => 'total_logs',
                'condition_value' => ['threshold' => 1000],
                'points_reward'   => 250,
                'tier'            => AchievementTier::Gold->value,
            ],
            [
                'name'            => 'Pill Tracker',
                'description'     => 'Log 30 medication doses.',
                'icon'            => '💊',
                'condition_type'  => 'medication_logs',
                'condition_value' => ['threshold' => 30],
                'points_reward'   => 25,
                'tier'            => AchievementTier::Bronze->value,
            ],
            [
                'name'            => 'Gut Instinct',
                'description'     => 'Log 50 excretion entries.',
                'icon'            => '🔬',
                'condition_type'  => 'excretion_logs',
                'condition_value' => ['threshold' => 50],
                'points_reward'   => 50,
                'tier'            => AchievementTier::Silver->value,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::updateOrCreate(
                ['name' => $achievement['name']],
                $achievement
            );
        }
    }
}
