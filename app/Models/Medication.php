<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Medication extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'name',
        'dosage',
        'unit',
        'frequency',
        'prescribed_by',
        'notes',
        'is_active',
        'reminder_times',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'reminder_times' => 'array',
    ];

    public function getNameAttribute(?string $value): ?string
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

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null ? Crypt::encryptString($value) : null;
    }

    public function getPrescribedByAttribute(?string $value): ?string
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

    public function setPrescribedByAttribute(?string $value): void
    {
        $this->attributes['prescribed_by'] = $value !== null ? Crypt::encryptString($value) : null;
    }

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

    public function medicationLogs(): HasMany
    {
        return $this->hasMany(MedicationLog::class);
    }
}
