<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class DailySummary extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'date',
        'total_calories',
        'total_water_ml',
        'exercise_minutes',
        'sleep_hours',
        'log_count',
        'points_earned',
        'data',
    ];

    protected $casts = [
        'date'             => 'date',
        'total_calories'   => 'integer',
        'total_water_ml'   => 'integer',
        'exercise_minutes' => 'integer',
        'sleep_hours'      => 'decimal:2',
        'log_count'        => 'integer',
        'points_earned'    => 'integer',
        'data'             => 'array',
    ];
}
