<?php

namespace App\Enums;

enum BloodAmount: string
{
    case None = 'none';
    case Trace = 'trace';
    case Moderate = 'moderate';
    case Heavy = 'heavy';
}
