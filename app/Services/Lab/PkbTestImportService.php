<?php

namespace App\Services\Lab;

use App\Enums\LabResultStatus;
use App\Models\LabTestDefinition;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Maps PKB's fetchTestHistoryJson payload onto normalised result rows and hands
 * them to LabResultImporter. Field mapping: docs/lab-results-design.md §2.5.
 *
 * Accepts any of:
 *   - a JSON string of any of the below
 *   - the console-snippet wrapper: [{testResultTypeId, source, data}, …]
 *   - a single raw payload: {count, tests:[…]}
 *   - an array of raw payloads
 */
class PkbTestImportService
{
    public function __construct(private readonly LabResultImporter $importer) {}

    /**
     * @param  string|array<mixed>  $payload
     * @return array{panels:int, results_created:int, results_updated:int}
     */
    public function import(User $user, string|array $payload): array
    {
        $rows = [];

        foreach ($this->extractPayloads($payload) as $doc) {
            foreach ($doc['tests'] ?? [] as $test) {
                foreach ($test['testHistoryMetadata'] ?? [] as $meta) {
                    foreach ($meta['dataPoints'] ?? [] as $point) {
                        $rows[] = $this->mapDataPoint($meta, $point);
                    }
                }
            }
        }

        return $this->importer->importResults($user, $rows);
    }

    /**
     * Normalise any accepted input into a flat list of raw payloads ({tests:[…]}).
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractPayloads(string|array $payload): array
    {
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?? [];
        }

        // Single raw payload.
        if (isset($payload['tests'])) {
            return [$payload];
        }

        // Wrapper list or list of raw payloads.
        $out = [];
        foreach ($payload as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (isset($item['data']['tests'])) {   // console-snippet wrapper
                $out[] = $item['data'];
            } elseif (isset($item['tests'])) {      // bare payload in a list
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta   one testHistoryMetadata (analyte)
     * @param  array<string, mixed>  $point  one dataPoint (measurement)
     * @return array<string, mixed>
     */
    private function mapDataPoint(array $meta, array $point): array
    {
        $pkbTypeId = isset($meta['testResultTypeId']) ? (string) $meta['testResultTypeId'] : null;
        $codeSystem = $meta['testResultType'] ?? null;
        $name = $meta['name'] ?? ($meta['testResultTypeName'] ?? 'Unknown');
        $unit = ($meta['unit'] ?? '') !== '' ? $meta['unit'] : null;

        $definition = LabTestDefinition::resolveForImport($pkbTypeId, $codeSystem, $name, $unit);

        $value = $point['value'] ?? [];
        $range = $point['range'] ?? [];
        $source = $point['source'] ?? [];

        $externalId = isset($point['id'])
            ? (string) $point['id']
            : sha1(($pkbTypeId ?? $name) . '|' . ($point['date']['value'] ?? '') . '|' . ($value['display'] ?? ''));

        return [
            'external_id' => $externalId,
            'test_definition_id' => $definition->id,
            'test_name' => $name,
            'test_code' => $pkbTypeId,
            // Canonical grouping key = the resolved definition's slug, so manual and
            // imported entries of the same analyte trend together (see §5).
            'test_key' => $definition->slug,

            'value_text' => $this->valueText($value),
            'value_numeric' => $value['rawNumericValue'] ?? null,
            'value_comparator' => ($value['comparator'] ?? null) ?: null,
            'unit' => $unit,

            'range_low' => $range['low'] ?? null,
            'range_high' => $range['high'] ?? null,
            'range_text' => $this->rangeText($range),

            'status' => $this->status($point),
            'textual_only' => (bool) ($meta['textualResultsOnly'] ?? false),

            'sampled_at' => $this->fromMs($point['date']['value'] ?? null),
            'released_at' => $this->fromMs($point['enteredDate']['value'] ?? null),
            'available_from' => $this->fromMs(
                $point['delayedDisplayDate']['value'] ?? $point['delayedDisplayDate'] ?? null
            ),
            'privacy_flags' => $point['privacyFlags'] ?? null,
            'comment' => ($point['comment']['comments'] ?? null) ?: null,

            // Panel hints (consumed by LabResultImporter, not stored on the result).
            'lab_order_id' => ($point['labOrderId'] ?? null) ?: null,
            'source' => 'pkb_json',
            'performing_org' => ($source['displayText'] ?? null) ?: null,
            'source_via' => ($source['via'] ?? null) ?: null,
            'lab_type' => ($point['labType'] ?? null) ?: null,
        ];
    }

    /** @param array<string, mixed> $value */
    private function valueText(array $value): string
    {
        $text = trim((string) ($value['display'] ?? ''));
        if ($text !== '') {
            return $text;
        }
        $localized = trim((string) ($value['localizedValue'] ?? ''));

        return $localized !== '' ? $localized : 'N/A';
    }

    /** @param array<string, mixed> $range */
    private function rangeText(array $range): ?string
    {
        if (! empty($range['textRange'])) {
            return $range['textRange'];
        }
        // Keep the display string only when there was no numeric range to store.
        if (empty($range['rangeProvided']) && ! empty($range['display'])) {
            return $range['display'];
        }

        return null;
    }

    /** @param array<string, mixed> $point */
    private function status(array $point): LabResultStatus
    {
        if (! empty($point['deleted'])) {
            return LabResultStatus::Withdrawn;
        }
        if (! empty($point['replaceDate']['value'])) {
            return LabResultStatus::Corrected;
        }

        return LabResultStatus::Final;
    }

    private function fromMs(mixed $ms): ?Carbon
    {
        return is_numeric($ms) ? Carbon::createFromTimestampMs((int) $ms) : null;
    }
}
