<?php

namespace App\Enums;

/**
 * Lifecycle status of a lab result, mapped from the source where available.
 */
enum LabResultStatus: string
{
    case Preliminary = 'preliminary';
    case Final = 'final';
    case Corrected = 'corrected'; // PKB "Corrected" / replaceDate present
    case Withdrawn = 'withdrawn'; // PKB deleted flag / "withdrawn by … on …"
}
