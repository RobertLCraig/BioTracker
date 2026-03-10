<?php

return [
    'points' => [
        'food' => 5,
        'drink' => 5,
        'exercise' => 10,
        'sleep' => 10,
        'excretion' => 5,
        'medication' => 5,
        'symptom' => 5,
        'vital' => 5,
        'photo_bonus' => 3,
    ],

    'streaks' => [
        'milestones' => [
            3 => 15,
            7 => 30,
            14 => 75,
            30 => 200,
            60 => 400,
            90 => 600,
            365 => 2000,
        ],
    ],

    'security' => [
        'session_timeout_minutes' => 15,
        'mfa_recovery_codes_count' => 8,
        'encrypted_fields' => true,
    ],
];
