<?php

namespace App\Services\Lab;

use App\Enums\AbnormalFlag;
use Illuminate\Support\Carbon;

/**
 * Best-effort parser for text copied from PKB's rendered "Latest" tests table.
 * This is the fallback path (JSON import is primary and far more reliable — see
 * docs/lab-results-design.md §3). It never persists; it returns candidate rows
 * for the user to review and confirm.
 *
 * Typical two-line shape per result:
 *   "Serum HDL cholesterol   17 Jul   0.8"
 *   "mmol/L   out of range"
 * Paste carries no numeric reference range, so a flag can only be Normal
 * ("in range"), Abnormal ("out of range"), or Unknown.
 */
class LabResultParser
{
    private const DATE_RE = '/\b(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)(?:\s+\d{4})?)\b/';

    private const NOISE = [
        'About test', 'Latest', 'Trend', 'Search for tests', 'Test Results',
        'Day', 'Week', 'Month', 'Year', 'All', 'Out of range', 'Range', 'Comment',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $text): array
    {
        $lines = $this->cleanLines($text);
        $rows = [];

        for ($i = 0; $i < count($lines); $i++) {
            if (! preg_match(self::DATE_RE, $lines[$i], $m, PREG_OFFSET_CAPTURE)) {
                continue; // not a result header
            }

            $dateStr = $m[1][0];
            $offset = $m[0][1];

            $name = trim(substr($lines[$i], 0, $offset));
            $valuePart = trim(substr($lines[$i], $offset + strlen($m[0][0])));

            if ($name === '') {
                continue;
            }

            // The next non-date line usually carries "unit  [trend]  [flag]".
            $detail = ($i + 1 < count($lines) && ! preg_match(self::DATE_RE, $lines[$i + 1]))
                ? $lines[$i + 1]
                : '';

            $rows[] = $this->buildRow($name, $dateStr, $valuePart, $detail);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function buildRow(string $name, string $dateStr, string $valuePart, string $detail): array
    {
        $comparator = null;
        if (preg_match('/^([<>])/', $valuePart, $cm)) {
            $comparator = $cm[1];
        }

        $numeric = preg_match('/-?\d+(?:\.\d+)?/', $valuePart, $nm) ? (float) $nm[0] : null;

        $flag = AbnormalFlag::Unknown;
        if (stripos($detail, 'out of range') !== false) {
            $flag = AbnormalFlag::Abnormal;
        } elseif (stripos($detail, 'in range') !== false) {
            $flag = AbnormalFlag::Normal;
        }

        $unit = $this->guessUnit($detail);
        $sampledAt = $this->parseDate($dateStr);

        $needsReview = $numeric === null || $unit === null || ! str_contains($dateStr, ' 20');

        return [
            'test_name' => $name,
            'sampled_at' => $sampledAt?->toIso8601String(),
            'value_text' => $valuePart !== '' ? $valuePart : 'N/A',
            'value_numeric' => $numeric,
            'value_comparator' => $comparator,
            'unit' => $unit,
            'abnormal_flag' => $flag->value,
            'needs_review' => $needsReview,
            'raw' => trim("$name $dateStr $valuePart | $detail"),
        ];
    }

    private function guessUnit(string $detail): ?string
    {
        $first = trim(explode(' ', trim($detail))[0] ?? '');
        // Units are the leading token and never these trend/flag words.
        if ($first === '' || in_array(strtolower($first), ['no', 'in', 'out', '+', '-'], true)) {
            return null;
        }

        return $first;
    }

    private function parseDate(string $dateStr): ?Carbon
    {
        try {
            return Carbon::parse($dateStr);
        } catch (\Exception) {
            return null;
        }
    }

    /** @return array<int, string> */
    private function cleanLines(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));
            if ($line === '' || in_array($line, self::NOISE, true)) {
                continue;
            }
            // Strip a trailing/embedded "About test" affordance.
            $line = trim(str_ireplace('About test', '', $line));
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }
}
