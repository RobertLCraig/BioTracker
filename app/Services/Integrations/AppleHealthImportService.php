<?php

namespace App\Services\Integrations;

use App\Enums\VitalType;
use App\Models\ActivityLog;
use App\Models\ActivityType;
use App\Models\User;
use App\Models\VitalLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Parses an Apple Health export XML file and maps records into BioTracker models.
 *
 * Supported Apple Health record types:
 *   HKQuantityTypeIdentifierHeartRate        → VitalLog (heart_rate)
 *   HKQuantityTypeIdentifierBodyMass         → VitalLog (weight)
 *   HKQuantityTypeIdentifierBodyTemperature  → VitalLog (temperature)
 *   HKQuantityTypeIdentifierBloodGlucose     → VitalLog (blood_sugar)
 *   HKQuantityTypeIdentifierOxygenSaturation → VitalLog (spo2)
 *   HKQuantityTypeIdentifierStepCount        → ActivityLog (exercise)
 *   HKCategoryTypeIdentifierSleepAnalysis    → ActivityLog (sleep)
 */
class AppleHealthImportService
{
    private const RECORD_MAP = [
        'HKQuantityTypeIdentifierHeartRate'        => ['model' => 'vital', 'type' => VitalType::HeartRate,   'unit' => 'bpm'],
        'HKQuantityTypeIdentifierBodyMass'          => ['model' => 'vital', 'type' => VitalType::Weight,      'unit' => 'kg'],
        'HKQuantityTypeIdentifierBodyTemperature'   => ['model' => 'vital', 'type' => VitalType::Temperature, 'unit' => '°C'],
        'HKQuantityTypeIdentifierBloodGlucose'      => ['model' => 'vital', 'type' => VitalType::BloodSugar,  'unit' => 'mmol/L'],
        'HKQuantityTypeIdentifierOxygenSaturation'  => ['model' => 'vital', 'type' => VitalType::SpO2,        'unit' => '%'],
        'HKQuantityTypeIdentifierStepCount'         => ['model' => 'activity', 'slug' => 'exercise'],
        'HKCategoryTypeIdentifierSleepAnalysis'     => ['model' => 'activity', 'slug' => 'sleep'],
    ];

    /**
     * Parse XML content and persist health records for the given user.
     * Returns counts of created and skipped records.
     *
     * @return array{created: int, skipped: int, errors: int}
     */
    public function import(User $user, string $xmlContent): array
    {
        $created = 0;
        $skipped = 0;
        $errors  = 0;

        try {
            $xml = new \SimpleXMLElement($xmlContent);
        } catch (\Exception $e) {
            Log::error('Apple Health XML parse failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            throw $e;
        }

        $activityTypeCache = [];

        foreach ($xml->Record as $record) {
            $type = (string) $record['type'];

            if (! isset(self::RECORD_MAP[$type])) {
                continue;
            }

            $map = self::RECORD_MAP[$type];

            try {
                if ($map['model'] === 'vital') {
                    $result = $this->importVital($user, $record, $map);
                } else {
                    $result = $this->importActivity($user, $record, $map, $activityTypeCache);
                }

                $result ? $created++ : $skipped++;
            } catch (\Throwable $e) {
                Log::warning('Apple Health record import failed', [
                    'user_id' => $user->id,
                    'type'    => $type,
                    'error'   => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    /**
     * Import a vital sign record. Returns true if created, false if skipped.
     */
    private function importVital(User $user, \SimpleXMLElement $record, array $map): bool
    {
        $loggedAt = Carbon::parse((string) $record['startDate']);
        $value    = (float) $record['value'];

        // SpO2 from Apple Health is 0–1; normalise to 0–100
        if ($map['type'] === VitalType::SpO2 && $value <= 1.0) {
            $value = round($value * 100, 1);
        }

        $exists = VitalLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('type', $map['type']->value)
            ->where('logged_at', $loggedAt)
            ->where('source', 'apple_health')
            ->exists();

        if ($exists) {
            return false;
        }

        VitalLog::create([
            'user_id'  => $user->id,
            'type'     => $map['type']->value,
            'value'    => $value,
            'unit'     => $map['unit'],
            'logged_at' => $loggedAt,
            'source'   => 'apple_health',
        ]);

        return true;
    }

    /**
     * Import an activity (steps or sleep) record. Returns true if created.
     */
    private function importActivity(User $user, \SimpleXMLElement $record, array $map, array &$cache): bool
    {
        $slug = $map['slug'];

        if (! isset($cache[$slug])) {
            $cache[$slug] = ActivityType::withoutGlobalScopes()
                ->where('slug', $slug)
                ->where('is_system', true)
                ->first();
        }

        $activityType = $cache[$slug];
        if (! $activityType) {
            return false;
        }

        $startDate = Carbon::parse((string) $record['startDate']);
        $endDate   = Carbon::parse((string) $record['endDate']);

        $exists = ActivityLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('activity_type_id', $activityType->id)
            ->where('logged_at', $startDate)
            ->where('metadata->source', 'apple_health')
            ->exists();

        if ($exists) {
            return false;
        }

        if ($slug === 'sleep') {
            $minutes = (int) $startDate->diffInMinutes($endDate);

            ActivityLog::create([
                'user_id'          => $user->id,
                'activity_type_id' => $activityType->id,
                'logged_at'        => $startDate,
                'duration_minutes' => $minutes,
                'metadata'         => ['source' => 'apple_health'],
            ]);
        } else {
            // Steps: store quantity
            ActivityLog::create([
                'user_id'          => $user->id,
                'activity_type_id' => $activityType->id,
                'logged_at'        => $startDate,
                'quantity'         => (float) $record['value'],
                'unit'             => 'steps',
                'metadata'         => ['source' => 'apple_health'],
            ]);
        }

        return true;
    }
}
