<?php

namespace App\Services\Scoring;

use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Database\Eloquent\Model;

/**
 * Awards points for logging activities, uploading photos, and hitting streak milestones.
 * Points configuration lives in config/biotracker.php.
 */
class ScoringService
{
    /**
     * Award points to a user for a logged entry.
     * The points value is looked up from config using the source model's table name.
     */
    public function award(User $user, Model $source, string $reason): UserPoint
    {
        $points = $this->pointsForSource($source);

        return UserPoint::create([
            'user_id'     => $user->id,
            'points'      => $points,
            'source_type' => get_class($source),
            'source_id'   => $source->getKey(),
            'reason'      => $reason,
        ]);
    }

    /**
     * Award a bonus for attaching a photo to a log entry.
     */
    public function awardPhotoBonus(User $user, Model $source): UserPoint
    {
        $points = (int) config('biotracker.points.photo_bonus', 3);

        return UserPoint::create([
            'user_id'     => $user->id,
            'points'      => $points,
            'source_type' => get_class($source),
            'source_id'   => $source->getKey(),
            'reason'      => 'Photo attached',
        ]);
    }

    /**
     * Award streak milestone bonus if the streak length matches a configured milestone.
     * Returns null if the streak day is not a milestone.
     */
    public function awardStreakBonus(User $user, int $streakDays): ?UserPoint
    {
        $milestones = config('biotracker.streaks.milestones', []);

        if (! isset($milestones[$streakDays])) {
            return null;
        }

        $points = (int) $milestones[$streakDays];

        return UserPoint::create([
            'user_id' => $user->id,
            'points'  => $points,
            'reason'  => "Streak milestone: {$streakDays} days",
        ]);
    }

    /**
     * Return the total points balance for a user.
     */
    public function getBalance(User $user): int
    {
        return (int) UserPoint::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->sum('points');
    }

    /**
     * Map a model instance to its points config key using the table name.
     */
    private function pointsForSource(Model $source): int
    {
        $tableToKey = [
            'activity_logs'  => 'food',   // Generic fallback; activity type points override this
            'excretion_logs' => 'excretion',
            'medication_logs'=> 'medication',
            'symptom_logs'   => 'symptom',
            'vital_logs'     => 'vital',
        ];

        $table = $source->getTable();
        $key   = $tableToKey[$table] ?? null;

        if ($key === null) {
            return 5; // safe default
        }

        // Activity logs use points_per_log from the activity type when available
        if ($table === 'activity_logs' && method_exists($source, 'activityType')) {
            $activityType = $source->relationLoaded('activityType')
                ? $source->activityType
                : $source->activityType()->first();

            if ($activityType) {
                return (int) $activityType->points_per_log;
            }
        }

        return (int) config("biotracker.points.{$key}", 5);
    }
}
