<?php

namespace App\Observers;

use App\Models\ActivityLog;

/**
 * Observes ActivityLog events to trigger scoring and streak updates.
 * Scoring/Streak services are wired in Phase 3 — stubs are used here
 * so Phase 2 can be fully functional without Phase 3 dependencies.
 */
class ActivityLogObserver
{
    public function created(ActivityLog $log): void
    {
        // Phase 3: app(ScoringService::class)->award($log->user, $log, 'Logged activity');
        // Phase 3: app(StreakService::class)->recordActivity($log->user);
        // Phase 3: if ($log->hasMedia('photos')) { app(ScoringService::class)->awardPhotoBonus($log->user, $log); }
        // Phase 3: app(AchievementService::class)->check($log->user);
    }
}
