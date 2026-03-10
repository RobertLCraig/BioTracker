<?php

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SymptomLog extends Model implements HasMedia
{
    use BelongsToUser, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'symptom',
        'severity',
        'body_area',
        'logged_at',
        'duration_minutes',
        'notes',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
        'severity' => 'integer',
        'duration_minutes' => 'integer',
    ];

    public function getSymptomAttribute(?string $value): ?string
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

    public function setSymptomAttribute(?string $value): void
    {
        $this->attributes['symptom'] = $value !== null ? Crypt::encryptString($value) : null;
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->onlyKeepLatest(10);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300);

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600);
    }
}
