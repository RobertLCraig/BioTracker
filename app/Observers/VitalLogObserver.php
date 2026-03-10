<?php

namespace App\Observers;

use App\Models\VitalLog;
use App\Services\Scoring\AchievementService;
use App\Services\Scoring\ScoringService;
use App\Services\Streak\StreakService;

class VitalLogObserver
{
    public function __construct(
        private ScoringService $scoring,
        private StreakService $streak,
        private AchievementService $achievements,
    ) {}

    public function created(VitalLog $log): void
    {
        $user = $log->user;

        $this->scoring->award($user, $log, 'Logged vital sign');
        $this->streak->recordActivity($user);
        $this->achievements->check($user);
    }
}
