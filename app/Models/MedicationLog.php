<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class MedicationLog extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'medication_id',
        'taken_at',
        'dosage_taken',
        'notes',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
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

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}
