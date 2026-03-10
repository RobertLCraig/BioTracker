<?php

namespace App\Services\Scoring;

use App\Models\Achievement;
use App\Models\ActivityLog;
use App\Models\ExcretionLog;
use App\Models\MedicationLog;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Checks all unearned achievements for a user and awards those whose conditions are met.
 */
class AchievementService
{
    public function __construct(private ScoringService $scoring) {}

    /**
     * Evaluate all unearned achievements and award any that are now met.
     *
     * @return Achievement[] newly unlocked achievements
     */
    public function check(User $user): array
    {
        $earnedIds = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->pluck('achievement_id')
            ->toArray();

        $candidates = Achievement::whereNotIn('id', $earnedIds)->get();
        $unlocked   = [];

        foreach ($candidates as $achievement) {
            if ($this->evaluate($user, $achievement)) {
                $this->award($user, $achievement);
                $unlocked[] = $achievement;
            }
        }

        return $unlocked;
    }

    /**
     * Evaluate a single achievement's condition for the given user.
     */
    private function evaluate(User $user, Achievement $achievement): bool
    {
        $threshold = (int) ($achievement->condition_value['threshold'] ?? 0);

        return match ($achievement->condition_type) {
            'total_logs'      => $this->totalLogs($user) >= $threshold,
            'streak'          => $this->currentStreak($user) >= $threshold,
            'total_points'    => $this->scoring->getBalance($user) >= $threshold,
            'excretion_logs'  => $this->countModel($user, ExcretionLog::class) >= $threshold,
            'medication_logs' => $this->countModel($user, MedicationLog::class) >= $threshold,
            'photos_attached' => $this->photosAttached($user) >= $threshold,
            default           => false,
        };
    }

    private function award(User $user, Achievement $achievement): void
    {
        DB::table('user_achievements')->insert([
            'user_id'        => $user->id,
            'achievement_id' => $achievement->id,
            'unlocked_at'    => Carbon::now(),
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ]);

        // Award the achievement's points reward
        $this->scoring->award(
            $user,
            $achievement,
            "Achievement unlocked: {$achievement->name}"
        );
    }

    private function totalLogs(User $user): int
    {
        return ActivityLog::withoutGlobalScopes()->where('user_id', $user->id)->count()
            + ExcretionLog::withoutGlobalScopes()->where('user_id', $user->id)->count()
            + MedicationLog::withoutGlobalScopes()->where('user_id', $user->id)->count();
    }

    private function currentStreak(User $user): int
    {
        return (int) DB::table('user_streaks')
            ->where('user_id', $user->id)
            ->value('current_streak');
    }

    private function countModel(User $user, string $modelClass): int
    {
        return $modelClass::withoutGlobalScopes()->where('user_id', $user->id)->count();
    }

    private function photosAttached(User $user): int
    {
        // Count all media items associated with this user's logs via morphMap
        return DB::table('media')
            ->whereIn('model_type', [ActivityLog::class, ExcretionLog::class])
            ->whereIn('model_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('activity_logs')
                    ->where('user_id', $user->id);
            })
            ->count()
            + DB::table('media')
            ->whereIn('model_type', [ExcretionLog::class])
            ->whereIn('model_id', function ($q) use ($user) {
                $q->select('id')
                    ->from('excretion_logs')
                    ->where('user_id', $user->id);
            })
            ->count();
    }
}
