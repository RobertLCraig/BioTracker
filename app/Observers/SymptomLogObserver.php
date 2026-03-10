<?php

namespace App\Observers;

use App\Models\SymptomLog;
use App\Services\Scoring\AchievementService;
use App\Services\Scoring\ScoringService;
use App\Services\Streak\StreakService;

class SymptomLogObserver
{
    public function __construct(
        private ScoringService $scoring,
        private StreakService $streak,
        private AchievementService $achievements,
    ) {}

    public function created(SymptomLog $log): void
    {
        $user = $log->user;

        $this->scoring->award($user, $log, 'Logged symptom');
        $this->streak->recordActivity($user);

        if ($log->hasMedia('photos')) {
            $this->scoring->awardPhotoBonus($user, $log);
        }

        $this->achievements->check($user);
    }
}
