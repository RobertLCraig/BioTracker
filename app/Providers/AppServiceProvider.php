<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\ExcretionLog;
use App\Models\MedicationLog;
use App\Models\SymptomLog;
use App\Models\VitalLog;
use App\Observers\ActivityLogObserver;
use App\Observers\ExcretionLogObserver;
use App\Observers\MedicationLogObserver;
use App\Observers\SymptomLogObserver;
use App\Observers\VitalLogObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ActivityLog::observe(ActivityLogObserver::class);
        ExcretionLog::observe(ExcretionLogObserver::class);
        MedicationLog::observe(MedicationLogObserver::class);
        SymptomLog::observe(SymptomLogObserver::class);
        VitalLog::observe(VitalLogObserver::class);
    }
}
