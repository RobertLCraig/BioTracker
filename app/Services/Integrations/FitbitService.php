<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationProvider;
use App\Enums\VitalType;
use App\Models\ActivityLog;
use App\Models\ConnectedService;
use App\Models\User;
use App\Models\VitalLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fitbit OAuth2 integration service.
 *
 * Configure in config/services.php:
 *   'fitbit' => [
 *       'client_id'     => env('FITBIT_CLIENT_ID'),
 *       'client_secret' => env('FITBIT_CLIENT_SECRET'),
 *       'redirect'      => env('FITBIT_REDIRECT_URI'),
 *   ]
 */
class FitbitService
{
    private const AUTH_URL  = 'https://www.fitbit.com/oauth2/authorize';
    private const TOKEN_URL = 'https://api.fitbit.com/oauth2/token';
    private const API_BASE  = 'https://api.fitbit.com/1';

    /**
     * Return the OAuth2 authorization URL.
     */
    public function getAuthUrl(): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => config('services.fitbit.client_id'),
            'redirect_uri'  => config('services.fitbit.redirect'),
            'scope'         => 'activity heartrate sleep weight profile',
            'expires_in'    => '604800',
        ]);

        return self::AUTH_URL . '?' . $params;
    }

    /**
     * Exchange an authorization code for tokens and persist the ConnectedService.
     */
    public function handleCallback(User $user, string $code): ConnectedService
    {
        $response = Http::asForm()->withBasicAuth(
            config('services.fitbit.client_id'),
            config('services.fitbit.client_secret'),
        )->post(self::TOKEN_URL, [
            'code'         => $code,
            'grant_type'   => 'authorization_code',
            'redirect_uri' => config('services.fitbit.redirect'),
        ]);

        $response->throw();
        $data = $response->json();

        return ConnectedService::updateOrCreate(
            ['user_id' => $user->id, 'provider' => IntegrationProvider::Fitbit->value],
            [
                'access_token'     => $data['access_token'],
                'refresh_token'    => $data['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 28800),
                'scopes'           => explode(' ', $data['scope'] ?? ''),
            ]
        );
    }

    /**
     * Pull data from Fitbit API and create VitalLog / ActivityLog entries.
     * Deduplicates by logged_at + type/source combination.
     */
    public function syncData(User $user): void
    {
        $service = ConnectedService::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('provider', IntegrationProvider::Fitbit->value)
            ->firstOrFail();

        if ($service->isTokenExpired()) {
            $this->refreshToken($service);
            $service->refresh();
        }

        $today = Carbon::today()->toDateString();

        try {
            $this->syncHeartRate($user, $service, $today);
            $this->syncWeight($user, $service, $today);
            $this->syncSleep($user, $service, $today);
            $this->syncSteps($user, $service, $today);
        } catch (\Throwable $e) {
            Log::error('Fitbit sync failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            throw $e;
        }

        $service->update(['last_synced_at' => now()]);
    }

    /**
     * Refresh an expired access token.
     */
    public function refreshToken(ConnectedService $service): void
    {
        $response = Http::asForm()->withBasicAuth(
            config('services.fitbit.client_id'),
            config('services.fitbit.client_secret'),
        )->post(self::TOKEN_URL, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $service->refresh_token,
        ]);

        $response->throw();
        $data = $response->json();

        $service->update([
            'access_token'     => $data['access_token'],
            'refresh_token'    => $data['refresh_token'] ?? $service->getRawOriginal('refresh_token'),
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 28800),
        ]);
    }

    // ── Private sync helpers ────────────────────────────────────────────────

    private function syncHeartRate(User $user, ConnectedService $service, string $date): void
    {
        $response = Http::withToken($service->access_token)
            ->get(self::API_BASE . "/user/-/activities/heart/date/{$date}/1d.json");

        if (! $response->successful()) {
            return;
        }

        $data = $response->json('activities-heart.0.value.restingHeartRate');
        if ($data === null) {
            return;
        }

        $loggedAt = Carbon::parse($date)->setTime(8, 0);
        $this->upsertVital($user, VitalType::HeartRate, (float) $data, null, 'bpm', $loggedAt);
    }

    private function syncWeight(User $user, ConnectedService $service, string $date): void
    {
        $response = Http::withToken($service->access_token)
            ->get(self::API_BASE . "/user/-/body/log/weight/date/{$date}.json");

        if (! $response->successful()) {
            return;
        }

        foreach ($response->json('weight', []) as $entry) {
            $loggedAt = Carbon::parse($entry['date'] . ' ' . $entry['time']);
            $this->upsertVital($user, VitalType::Weight, (float) $entry['weight'], null, 'kg', $loggedAt);
        }
    }

    private function syncSleep(User $user, ConnectedService $service, string $date): void
    {
        $response = Http::withToken($service->access_token)
            ->get(self::API_BASE . "/user/-/sleep/date/{$date}.json");

        if (! $response->successful()) {
            return;
        }

        $minutes = $response->json('summary.totalMinutesAsleep');
        if ($minutes === null) {
            return;
        }

        // Store as an ActivityLog for the 'sleep' activity type
        // We look up the system sleep type by slug
        $sleepType = \App\Models\ActivityType::withoutGlobalScopes()
            ->where('slug', 'sleep')
            ->where('is_system', true)
            ->first();

        if (! $sleepType) {
            return;
        }

        $loggedAt = Carbon::parse($date)->setTime(7, 0);

        $exists = ActivityLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('activity_type_id', $sleepType->id)
            ->whereDate('logged_at', $date)
            ->where('metadata->source', 'fitbit')
            ->exists();

        if (! $exists) {
            ActivityLog::create([
                'user_id'          => $user->id,
                'activity_type_id' => $sleepType->id,
                'logged_at'        => $loggedAt,
                'duration_minutes' => (int) $minutes,
                'metadata'         => ['source' => 'fitbit'],
            ]);
        }
    }

    private function syncSteps(User $user, ConnectedService $service, string $date): void
    {
        $response = Http::withToken($service->access_token)
            ->get(self::API_BASE . "/user/-/activities/steps/date/{$date}/1d.json");

        if (! $response->successful()) {
            return;
        }

        $steps = $response->json('activities-steps.0.value');
        if ($steps === null || (int) $steps === 0) {
            return;
        }

        $exerciseType = \App\Models\ActivityType::withoutGlobalScopes()
            ->where('slug', 'exercise')
            ->where('is_system', true)
            ->first();

        if (! $exerciseType) {
            return;
        }

        $loggedAt = Carbon::parse($date)->setTime(12, 0);

        $exists = ActivityLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('activity_type_id', $exerciseType->id)
            ->whereDate('logged_at', $date)
            ->where('metadata->fitbit_steps', '!=', null)
            ->exists();

        if (! $exists) {
            ActivityLog::create([
                'user_id'          => $user->id,
                'activity_type_id' => $exerciseType->id,
                'logged_at'        => $loggedAt,
                'quantity'         => (int) $steps,
                'unit'             => 'steps',
                'metadata'         => ['source' => 'fitbit', 'fitbit_steps' => (int) $steps],
            ]);
        }
    }

    private function upsertVital(
        User $user,
        VitalType $type,
        float $value,
        ?string $secondaryValue,
        string $unit,
        Carbon $loggedAt,
    ): void {
        $exists = VitalLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->where('logged_at', $loggedAt)
            ->where('source', 'fitbit')
            ->exists();

        if (! $exists) {
            VitalLog::create([
                'user_id'         => $user->id,
                'type'            => $type->value,
                'value'           => $value,
                'secondary_value' => $secondaryValue,
                'unit'            => $unit,
                'logged_at'       => $loggedAt,
                'source'          => 'fitbit',
            ]);
        }
    }
}
