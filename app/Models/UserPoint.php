<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserPoint extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'points',
        'source_type',
        'source_id',
        'reason',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Sum of all points for the current user (respects global scope).
     */
    public function scopeTotalForUser(Builder $query): int
    {
        return (int) $query->sum('points');
    }
}
