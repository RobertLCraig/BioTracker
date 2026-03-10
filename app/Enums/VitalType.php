<?php

namespace App\Enums;

enum VitalType: string
{
    case Weight = 'weight';
    case BloodPressure = 'blood_pressure';
    case Temperature = 'temperature';
    case HeartRate = 'heart_rate';
    case BloodSugar = 'blood_sugar';
    case SpO2 = 'spo2';
}
