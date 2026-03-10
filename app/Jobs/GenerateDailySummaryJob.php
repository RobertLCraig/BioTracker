<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Regenerates yesterday's daily summary for all users.
 * Scheduled to run daily at 02:00 — see routes/console.php.
 */
class GenerateDailySummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function handle(AnalyticsService $analytics): void
    {
        $yesterday = Carbon::yesterday();

        User::query()->each(function (User $user) use ($analytics, $yesterday) {
            try {
                $analytics->regenerateDailySummary($user, $yesterday);
            } catch (\Throwable $e) {
                Log::error('Failed to generate daily summary', [
                    'user_id' => $user->id,
                    'date'    => $yesterday->toDateString(),
                    'error'   => $e->getMessage(),
                ]);
            }
        });
    }
}
