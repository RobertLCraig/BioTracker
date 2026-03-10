<?php

namespace App\Jobs;

use App\Models\ConnectedService;
use App\Models\User;
use App\Services\Integrations\FitbitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job to sync a single user's Fitbit data.
 * Handles token refresh automatically before syncing.
 * Scheduled per connected user every 4 hours via a custom artisan command.
 */
class SyncFitbitDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(public readonly User $user) {}

    public function handle(FitbitService $fitbit): void
    {
        try {
            $fitbit->syncData($this->user);
        } catch (\Throwable $e) {
            Log::error('SyncFitbitDataJob failed', [
                'user_id' => $this->user->id,
                'error'   => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
