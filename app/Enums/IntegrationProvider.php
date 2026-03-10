<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Fitbit = 'fitbit';
    case AppleHealth = 'apple_health';
    case HealthConnect = 'health_connect';
}
