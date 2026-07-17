<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * One lab order / collection event. Inferred from the results it groups
 * (see LabResultImporter). Encrypted free-text notes, per-user isolation.
 */
class LabPanel extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'name',
        'lab_order_id',
        'collected_at',
        'reported_at',
        'source',
        'performing_org',
        'source_via',
        'lab_type',
        'notes',
        'client_id',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class);
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
}
