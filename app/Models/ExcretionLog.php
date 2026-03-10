<?php

namespace App\Models;

use App\Enums\BloodAmount;
use App\Enums\BristolScale;
use App\Enums\ExcretionSize;
use App\Enums\ExcretionType;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ExcretionLog extends Model implements HasMedia
{
    use BelongsToUser, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'type',
        'size',
        'consistency',
        'colour',
        'has_blood',
        'blood_amount',
        'urgency',
        'pain_level',
        'logged_at',
        'notes',
    ];

    protected $casts = [
        'type' => ExcretionType::class,
        'size' => ExcretionSize::class,
        'consistency' => BristolScale::class,
        'blood_amount' => BloodAmount::class,
        'has_blood' => 'boolean',
        'logged_at' => 'datetime',
        'urgency' => 'integer',
        'pain_level' => 'integer',
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
