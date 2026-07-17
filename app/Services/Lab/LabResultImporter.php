<?php

namespace App\Services\Lab;

use App\Models\LabPanel;
use App\Models\LabResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared persistence core for lab results, regardless of source (PKB JSON,
 * manual, paste). Takes already-normalised result rows, infers/upserts panels,
 * and idempotently upserts results by (user_id, external_id).
 *
 * A row is an associative array of lab_results columns plus panel hints:
 *   lab_order_id, source, performing_org, source_via, lab_type.
 */
class LabResultImporter
{
    /** @var list<string> Columns that belong to lab_results (not the panel). */
    private const RESULT_KEYS = [
        'external_id', 'test_definition_id', 'test_name', 'test_code', 'test_key',
        'value_text', 'value_numeric', 'value_comparator', 'unit',
        'range_low', 'range_high', 'range_text', 'abnormal_flag', 'status',
        'textual_only', 'sampled_at', 'released_at', 'available_from',
        'privacy_flags', 'comment',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{panels:int, results_created:int, results_updated:int}
     */
    public function importResults(User $user, array $rows): array
    {
        $panels = [];   // lab_order_id => LabPanel
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($user, $rows, &$panels, &$created, &$updated) {
            foreach ($rows as $row) {
                $panel = $this->resolvePanel($user, $row, $panels);

                $attributes = array_intersect_key($row, array_flip(self::RESULT_KEYS));
                $attributes['user_id'] = $user->id;
                $attributes['lab_panel_id'] = $panel?->id;

                $existing = LabResult::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->where('external_id', $row['external_id'] ?? null)
                    ->first();

                if ($existing && ($row['external_id'] ?? null) !== null) {
                    $existing->fill($attributes)->save();
                    $updated++;
                } else {
                    LabResult::create($attributes);
                    $created++;
                }
            }
        });

        return [
            'panels' => count($panels),
            'results_created' => $created,
            'results_updated' => $updated,
        ];
    }

    /**
     * Upsert the inferred panel for a row, keyed on lab_order_id per user.
     * Returns null for standalone results with no order id.
     *
     * @param  array<string, LabPanel>  $panels
     */
    private function resolvePanel(User $user, array $row, array &$panels): ?LabPanel
    {
        $orderId = $row['lab_order_id'] ?? null;
        if ($orderId === null || $orderId === '') {
            return null;
        }

        if (! isset($panels[$orderId])) {
            $panels[$orderId] = LabPanel::withoutGlobalScopes()->updateOrCreate(
                ['user_id' => $user->id, 'client_id' => $orderId],
                [
                    'lab_order_id' => $orderId,
                    'name' => $row['panel_name'] ?? null,
                    'collected_at' => $row['sampled_at'] ?? null,
                    'reported_at' => $row['released_at'] ?? null,
                    'source' => $row['source'] ?? 'pkb_json',
                    'performing_org' => $row['performing_org'] ?? null,
                    'source_via' => $row['source_via'] ?? null,
                    'lab_type' => $row['lab_type'] ?? null,
                ],
            );
        }

        return $panels[$orderId];
    }
}
