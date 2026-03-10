<?php

namespace App\Observers;

use App\Models\MedicationLog;
use App\Services\Scoring\AchievementService;
use App\Services\Scoring\ScoringService;
use App\Services\Streak\StreakService;

class MedicationLogObserver
{
    public function __construct(
        private ScoringService $scoring,
        private StreakService $streak,
        private AchievementService $achievements,
    ) {}

    public function created(MedicationLog $log): void
    {
        $user = $log->user;

        $this->scoring->award($user, $log, 'Logged medication');
        $this->streak->recordActivity($user);
        $this->achievements->check($user);
    }
}
