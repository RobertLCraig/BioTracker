<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ConnectedService extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'provider',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'last_synced_at',
    ];

    protected $casts = [
        'provider'          => IntegrationProvider::class,
        'token_expires_at'  => 'datetime',
        'last_synced_at'    => 'datetime',
        'scopes'            => 'array',
    ];

    public function getAccessTokenAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return $value;
        }
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value !== null ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return $value;
        }
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value !== null ? Crypt::encryptString($value) : null;
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
