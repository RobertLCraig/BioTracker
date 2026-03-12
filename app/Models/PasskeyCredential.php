<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasskeyCredential extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'credential_id',
        'credential_source',
        'last_used_at',
    ];

    protected $casts = [
        'credential_source' => 'array',
        'last_used_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
