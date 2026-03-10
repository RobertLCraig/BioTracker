<?php

namespace App\Services\Streak;

use App\Models\User;
use App\Models\UserStreak;
use App\Services\Scoring\ScoringService;
use Illuminate\Support\Carbon;

/**
 * Manages daily activity streaks.
 * Rules:
 *   - Same day  → no change (already counted today)
 *   - Yesterday → increment streak
 *   - Older     → reset streak to 1
 */
class StreakService
{
    public function __construct(private ScoringService $scoring) {}

    /**
     * Record that the user logged an activity today.
     * Returns the updated (or newly created) streak record.
     */
    public function recordActivity(User $user): UserStreak
    {
        $streak = UserStreak::withoutGlobalScopes()
            ->firstOrCreate(
                ['user_id' => $user->id],
                ['current_streak' => 0, 'longest_streak' => 0]
            );

        $today     = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();
        $lastDate  = $streak->last_logged_date?->toDateString();

        if ($lastDate === $today) {
            // Already logged today — no change
            return $streak;
        }

        if ($lastDate === $yesterday) {
            // Consecutive day — extend streak
            $streak->current_streak += 1;
        } else {
            // Gap or first ever log — reset
            $streak->current_streak = 1;
        }

        if ($streak->current_streak > $streak->longest_streak) {
            $streak->longest_streak = $streak->current_streak;
        }

        $streak->last_logged_date = $today;
        $streak->save();

        $this->checkMilestones($user, $streak);

        return $streak;
    }

    /**
     * Award streak milestone bonus if the current streak hits a config milestone.
     */
    public function checkMilestones(User $user, UserStreak $streak): void
    {
        $this->scoring->awardStreakBonus($user, $streak->current_streak);
    }
}
