<?php

namespace App\Observers;

use App\Models\MedicationLog;

/**
 * Observes MedicationLog events to trigger scoring and streak updates.
 * Scoring/Streak services are wired in Phase 3.
 */
class MedicationLogObserver
{
    public function created(MedicationLog $log): void
    {
        // Phase 3: app(ScoringService::class)->award($log->user, $log, 'Logged medication');
        // Phase 3: app(StreakService::class)->recordActivity($log->user);
    }
}
