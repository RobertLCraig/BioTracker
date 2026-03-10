<?php

namespace App\Observers;

use App\Models\ExcretionLog;

/**
 * Observes ExcretionLog events to trigger scoring and streak updates.
 * Scoring/Streak services are wired in Phase 3.
 */
class ExcretionLogObserver
{
    public function created(ExcretionLog $log): void
    {
        // Phase 3: app(ScoringService::class)->award($log->user, $log, 'Logged excretion');
        // Phase 3: app(StreakService::class)->recordActivity($log->user);
    }
}
