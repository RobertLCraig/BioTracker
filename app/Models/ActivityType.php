<?php

namespace App\Models;

use App\Models\Scopes\UserOwnedScope;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityType extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'points_per_log',
        'is_system',
        'user_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'points_per_log' => 'integer',
    ];

    /**
     * Override boot to conditionally apply scope.
     * System types are visible to all users.
     */
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new UserOwnedScope);
        static::creating(function (Model $model) {
            if (auth()->check() && ! $model->user_id && ! $model->is_system) {
                $model->user_id = auth()->id();
            }
        });
    }

    /**
     * Scope: return system activity types plus the current user's custom types.
     */
    public function scopeForUser(Builder $query): Builder
    {
        return $query->withoutGlobalScope(UserOwnedScope::class)
            ->where(function (Builder $q) {
                $q->where('is_system', true)
                    ->orWhere('user_id', auth()->id());
            });
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
