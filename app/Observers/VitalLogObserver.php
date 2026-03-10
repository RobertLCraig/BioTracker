<?php

namespace App\Observers;

use App\Models\VitalLog;

/**
 * Observes VitalLog events to trigger scoring and streak updates.
 * Scoring/Streak services are wired in Phase 3.
 */
class VitalLogObserver
{
    public function created(VitalLog $log): void
    {
        // Phase 3: app(ScoringService::class)->award($log->user, $log, 'Logged vital sign');
        // Phase 3: app(StreakService::class)->recordActivity($log->user);
    }
}
