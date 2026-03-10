<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExcretionLog;
use App\Models\MedicationLog;
use App\Models\SymptomLog;
use App\Models\VitalLog;
use App\Services\Scoring\ScoringService;
use App\Services\Scoring\AchievementService;
use App\Services\Streak\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Handles bulk/batch imports for all five log types.
 * Each item in the array may include a client_id for idempotent dedup.
 *
 * Routes:
 *   POST /api/v1/activity-logs/batch
 *   POST /api/v1/excretion-logs/batch
 *   POST /api/v1/medication-logs/batch
 *   POST /api/v1/symptom-logs/batch
 *   POST /api/v1/vital-logs/batch
 */
class BatchImportController extends Controller
{
    public function __construct(
        private ScoringService     $scoring,
        private StreakService      $streak,
        private AchievementService $achievements,
    ) {}

    // ── Activity Logs ────────────────────────────────────────────────────────

    public function activityLogs(Request $request): JsonResponse
    {
        $request->validate([
            'items'                        => 'required|array|max:200',
            'items.*.activity_type_id'     => 'required|integer|exists:activity_types,id',
            'items.*.logged_at'            => 'required|date',
            'items.*.client_id'            => 'nullable|string|max:191',
            'items.*.duration_minutes'     => 'nullable|integer|min:0',
            'items.*.quantity'             => 'nullable|numeric',
            'items.*.unit'                 => 'nullable|string|max:50',
            'items.*.calories'             => 'nullable|integer|min:0',
            'items.*.notes'                => 'nullable|string',
        ]);

        $user    = $request->user();
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $user, &$created, &$skipped) {
            foreach ($request->input('items') as $item) {
                $clientId = $item['client_id'] ?? null;

                // Dedup via metadata->client_id
                if ($clientId) {
                    $exists = ActivityLog::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('metadata->client_id', $clientId)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                $metadata = $clientId ? ['client_id' => $clientId] : null;

                $log = ActivityLog::create([
                    'user_id'          => $user->id,
                    'activity_type_id' => $item['activity_type_id'],
                    'logged_at'        => $item['logged_at'],
                    'duration_minutes' => $item['duration_minutes'] ?? null,
                    'quantity'         => $item['quantity'] ?? null,
                    'unit'             => $item['unit'] ?? null,
                    'calories'         => $item['calories'] ?? null,
                    'notes'            => $item['notes'] ?? null,
                    'metadata'         => $metadata,
                ]);

                $this->awardPoints($user, $log);
                $created++;
            }
        });

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    // ── Excretion Logs ───────────────────────────────────────────────────────

    public function excretionLogs(Request $request): JsonResponse
    {
        $request->validate([
            'items'                    => 'required|array|max:200',
            'items.*.logged_at'        => 'required|date',
            'items.*.client_id'        => 'nullable|string|max:191',
            'items.*.type'             => 'required|in:pee,poop',
            'items.*.size'             => 'nullable|in:small,medium,large',
            'items.*.consistency'      => 'nullable|integer|min:1|max:7',
            'items.*.colour'           => 'nullable|string|max:50',
            'items.*.has_blood'        => 'nullable|boolean',
            'items.*.blood_amount'     => 'nullable|in:none,trace,moderate,heavy',
            'items.*.urgency'          => 'nullable|integer|min:1|max:5',
            'items.*.pain_level'       => 'nullable|integer|min:0|max:10',
            'items.*.notes'            => 'nullable|string',
        ]);

        $user    = $request->user();
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $user, &$created, &$skipped) {
            foreach ($request->input('items') as $item) {
                $clientId = $item['client_id'] ?? null;

                if ($clientId) {
                    $exists = ExcretionLog::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('client_id', $clientId)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                $log = ExcretionLog::create([
                    'user_id'      => $user->id,
                    'client_id'    => $clientId,
                    'logged_at'    => $item['logged_at'],
                    'type'         => $item['type'],
                    'size'         => $item['size'] ?? null,
                    'consistency'  => $item['consistency'] ?? null,
                    'colour'       => $item['colour'] ?? null,
                    'has_blood'    => $item['has_blood'] ?? false,
                    'blood_amount' => $item['blood_amount'] ?? 'none',
                    'urgency'      => $item['urgency'] ?? null,
                    'pain_level'   => $item['pain_level'] ?? null,
                    'notes'        => $item['notes'] ?? null,
                ]);

                $this->awardPoints($user, $log);
                $created++;
            }
        });

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    // ── Medication Logs ──────────────────────────────────────────────────────

    public function medicationLogs(Request $request): JsonResponse
    {
        $request->validate([
            'items'                    => 'required|array|max:200',
            'items.*.medication_id'    => 'required|integer|exists:medications,id',
            'items.*.taken_at'         => 'required|date',
            'items.*.client_id'        => 'nullable|string|max:191',
            'items.*.dosage_taken'     => 'nullable|string|max:100',
            'items.*.notes'            => 'nullable|string',
        ]);

        $user    = $request->user();
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $user, &$created, &$skipped) {
            foreach ($request->input('items') as $item) {
                $clientId = $item['client_id'] ?? null;

                if ($clientId) {
                    $exists = MedicationLog::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('client_id', $clientId)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                $log = MedicationLog::create([
                    'user_id'       => $user->id,
                    'client_id'     => $clientId,
                    'medication_id' => $item['medication_id'],
                    'taken_at'      => $item['taken_at'],
                    'dosage_taken'  => $item['dosage_taken'] ?? null,
                    'notes'         => $item['notes'] ?? null,
                ]);

                $this->awardPoints($user, $log);
                $created++;
            }
        });

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    // ── Symptom Logs ─────────────────────────────────────────────────────────

    public function symptomLogs(Request $request): JsonResponse
    {
        $request->validate([
            'items'                       => 'required|array|max:200',
            'items.*.logged_at'           => 'required|date',
            'items.*.client_id'           => 'nullable|string|max:191',
            'items.*.symptom'             => 'required|string|max:255',
            'items.*.severity'            => 'required|integer|min:1|max:10',
            'items.*.body_area'           => 'nullable|string|max:100',
            'items.*.duration_minutes'    => 'nullable|integer|min:0',
            'items.*.notes'               => 'nullable|string',
        ]);

        $user    = $request->user();
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $user, &$created, &$skipped) {
            foreach ($request->input('items') as $item) {
                $clientId = $item['client_id'] ?? null;

                if ($clientId) {
                    $exists = SymptomLog::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('client_id', $clientId)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                $log = SymptomLog::create([
                    'user_id'          => $user->id,
                    'client_id'        => $clientId,
                    'logged_at'        => $item['logged_at'],
                    'symptom'          => $item['symptom'],
                    'severity'         => $item['severity'],
                    'body_area'        => $item['body_area'] ?? null,
                    'duration_minutes' => $item['duration_minutes'] ?? null,
                    'notes'            => $item['notes'] ?? null,
                ]);

                $this->awardPoints($user, $log);
                $created++;
            }
        });

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    // ── Vital Logs ───────────────────────────────────────────────────────────

    public function vitalLogs(Request $request): JsonResponse
    {
        $request->validate([
            'items'                    => 'required|array|max:200',
            'items.*.logged_at'        => 'required|date',
            'items.*.client_id'        => 'nullable|string|max:191',
            'items.*.type'             => 'required|in:weight,blood_pressure,temperature,heart_rate,blood_sugar,spo2',
            'items.*.value'            => 'required|numeric',
            'items.*.secondary_value'  => 'nullable|string|max:50',
            'items.*.unit'             => 'required|string|max:20',
            'items.*.source'           => 'nullable|string|max:50',
            'items.*.notes'            => 'nullable|string',
        ]);

        $user    = $request->user();
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $user, &$created, &$skipped) {
            foreach ($request->input('items') as $item) {
                $clientId = $item['client_id'] ?? null;

                if ($clientId) {
                    $exists = VitalLog::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('client_id', $clientId)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                $log = VitalLog::create([
                    'user_id'         => $user->id,
                    'client_id'       => $clientId,
                    'logged_at'       => $item['logged_at'],
                    'type'            => $item['type'],
                    'value'           => $item['value'],
                    'secondary_value' => $item['secondary_value'] ?? null,
                    'unit'            => $item['unit'],
                    'source'          => $item['source'] ?? 'manual',
                    'notes'           => $item['notes'] ?? null,
                ]);

                $this->awardPoints($user, $log);
                $created++;
            }
        });

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function awardPoints(\App\Models\User $user, \Illuminate\Database\Eloquent\Model $log): void
    {
        $this->scoring->award($user, $log, 'batch_import');
        $streak = $this->streak->recordActivity($user);
        $this->streak->checkMilestones($user, $streak);
        $this->achievements->check($user);
    }
}
