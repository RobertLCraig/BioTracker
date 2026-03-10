<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class UserStreak extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'current_streak',
        'longest_streak',
        'last_logged_date',
    ];

    protected $casts = [
        'current_streak'  => 'integer',
        'longest_streak'  => 'integer',
        'last_logged_date' => 'date',
    ];

    /**
     * A streak is active when the last log was today or yesterday.
     */
    public function isActive(): bool
    {
        if ($this->last_logged_date === null) {
            return false;
        }

        return $this->last_logged_date->gte(Carbon::today()->subDay());
    }
}
