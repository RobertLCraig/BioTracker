<?php

namespace App\Models;

use App\Enums\VitalType;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class VitalLog extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'type',
        'value',
        'secondary_value',
        'unit',
        'logged_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'type' => VitalType::class,
        'logged_at' => 'datetime',
        'value' => 'decimal:2',
    ];

    public function getNotesAttribute(?string $value): ?string
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

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = $value !== null ? Crypt::encryptString($value) : null;
    }
}
