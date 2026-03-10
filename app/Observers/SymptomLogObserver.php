<?php

namespace App\Observers;

use App\Models\SymptomLog;

/**
 * Observes SymptomLog events to trigger scoring and streak updates.
 * Scoring/Streak services are wired in Phase 3.
 */
class SymptomLogObserver
{
    public function created(SymptomLog $log): void
    {
        // Phase 3: app(ScoringService::class)->award($log->user, $log, 'Logged symptom');
        // Phase 3: app(StreakService::class)->recordActivity($log->user);
    }
}
