<?php

use App\Enums\IntegrationProvider;
use App\Jobs\GenerateDailySummaryJob;
use App\Jobs\SyncFitbitDataJob;
use App\Models\ConnectedService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Regenerate yesterday's daily summaries for all users at 02:00 every night
Schedule::job(GenerateDailySummaryJob::class)->dailyAt('02:00');

// Sync Fitbit data every 4 hours for all connected users
Schedule::call(function () {
    ConnectedService::withoutGlobalScopes()
        ->where('provider', IntegrationProvider::Fitbit->value)
        ->with('user')
        ->get()
        ->each(fn ($service) => SyncFitbitDataJob::dispatch($service->user));
})->everyFourHours()->name('sync-fitbit-all-users');
