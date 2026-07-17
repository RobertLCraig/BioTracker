<?php

namespace App\Models;

use App\Enums\AbnormalFlag;
use App\Enums\LabResultStatus;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * One analyte measurement. PKB field mapping: docs/lab-results-design.md §2.5.
 * On save, derives test_key (when empty) and abnormal_flag (when still unknown).
 */
class LabResult extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'lab_panel_id',
        'test_definition_id',
        'external_id',
        'test_name',
        'test_code',
        'test_key',
        'value_text',
        'value_numeric',
        'value_comparator',
        'unit',
        'range_low',
        'range_high',
        'range_text',
        'abnormal_flag',
        'status',
        'textual_only',
        'sampled_at',
        'released_at',
        'available_from',
        'privacy_flags',
        'comment',
    ];

    protected $casts = [
        'value_numeric' => 'decimal:4',
        'range_low' => 'decimal:4',
        'range_high' => 'decimal:4',
        'abnormal_flag' => AbnormalFlag::class,
        'status' => LabResultStatus::class,
        'textual_only' => 'boolean',
        'privacy_flags' => 'array',
        'sampled_at' => 'datetime',
        'released_at' => 'datetime',
        'available_from' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (LabResult $result) {
            if (empty($result->test_key)) {
                $result->test_key = $result->test_code ?: Str::slug((string) $result->test_name);
            }

            // Derive the out-of-range flag when it hasn't been set explicitly.
            if ($result->abnormal_flag === null || $result->abnormal_flag === AbnormalFlag::Unknown) {
                $result->abnormal_flag = AbnormalFlag::derive(
                    $result->value_numeric !== null ? (float) $result->value_numeric : null,
                    $result->range_low !== null ? (float) $result->range_low : null,
                    $result->range_high !== null ? (float) $result->range_high : null,
                );
            }
        });
    }

    public function labPanel(): BelongsTo
    {
        return $this->belongsTo(LabPanel::class);
    }

    public function testDefinition(): BelongsTo
    {
        return $this->belongsTo(LabTestDefinition::class, 'test_definition_id');
    }

    public function getCommentAttribute(?string $value): ?string
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

    public function setCommentAttribute(?string $value): void
    {
        $this->attributes['comment'] = $value !== null ? Crypt::encryptString($value) : null;
    }
}
