<?php

namespace App\Enums;

/**
 * Out-of-range flag for a lab result.
 *
 * PKB (and most portals) do not send an explicit flag — it is derived from the
 * value against its reference range. Kept as a stored enum (not a pure accessor)
 * so a future source that supplies its own HL7 flag can be honoured directly.
 */
enum AbnormalFlag: string
{
    case Normal = 'normal';              // HL7 N — within range
    case High = 'high';                  // HL7 H
    case Low = 'low';                    // HL7 L
    case CriticalHigh = 'critical_high'; // HL7 HH
    case CriticalLow = 'critical_low';   // HL7 LL
    case Abnormal = 'abnormal';          // HL7 A — non-numeric abnormal
    case Unknown = 'unknown';            // no numeric value or no range

    /**
     * Derive a flag from a numeric value against a reference range.
     * Returns Unknown when either the value or a bound is missing.
     */
    public static function derive(?float $value, ?float $low, ?float $high): self
    {
        if ($value === null || ($low === null && $high === null)) {
            return self::Unknown;
        }

        if ($low !== null && $value < $low) {
            return self::Low;
        }

        if ($high !== null && $value > $high) {
            return self::High;
        }

        return self::Normal;
    }
}
