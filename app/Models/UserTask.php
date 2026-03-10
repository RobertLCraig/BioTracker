<?php

namespace App\Models;

use App\Enums\TaskFrequency;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserTask extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'points_reward',
        'is_recurring',
        'frequency',
        'is_active',
    ];

    protected $casts = [
        'points_reward' => 'integer',
        'is_recurring'  => 'boolean',
        'is_active'     => 'boolean',
        'frequency'     => TaskFrequency::class,
    ];

    public function completions(): HasMany
    {
        return $this->hasMany(UserTaskCompletion::class);
    }
}
