<?php

namespace App\Models;

use App\Enums\AchievementTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    protected $fillable = [
        'name',
        'description',
        'icon',
        'condition_type',
        'condition_value',
        'points_reward',
        'tier',
    ];

    protected $casts = [
        'condition_value' => 'array',
        'points_reward'   => 'integer',
        'tier'            => AchievementTier::class,
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
