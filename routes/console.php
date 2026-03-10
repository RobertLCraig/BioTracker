<?php

use App\Jobs\GenerateDailySummaryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Regenerate yesterday's daily summaries for all users at 02:00 every night
Schedule::job(GenerateDailySummaryJob::class)->dailyAt('02:00');
