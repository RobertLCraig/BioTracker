<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Seeded analyte catalog (shared reference data — NOT user-owned).
 * Lab results link here so the same analyte trends consistently across labs.
 */
class LabTestDefinition extends Model
{
    protected $fillable = [
        'pkb_type_id',
        'code_system',
        'code',
        'name',
        'slug',
        'aliases',
        'category',
        'default_unit',
        'default_range_low',
        'default_range_high',
        'is_curated',
    ];

    protected $casts = [
        'aliases' => 'array',
        'is_curated' => 'boolean',
        'default_range_low' => 'decimal:4',
        'default_range_high' => 'decimal:4',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class, 'test_definition_id');
    }

    /**
     * Find (or non-lossily create) the definition for an incoming analyte.
     * Match order: PKB testResultTypeId → name slug/aliases → auto-create uncurated.
     */
    public static function resolveForImport(
        ?string $pkbTypeId,
        ?string $codeSystem,
        string $name,
        ?string $unit = null,
    ): self {
        if ($pkbTypeId !== null && $pkbTypeId !== '') {
            $byId = static::where('pkb_type_id', $pkbTypeId)->first();
            if ($byId) {
                return $byId;
            }
        }

        $slug = Str::slug($name) ?: 'unknown';

        $byName = static::where('slug', $slug)->first();
        if ($byName) {
            // Backfill the PKB id onto a curated seed the first time we see it.
            if ($pkbTypeId && ! $byName->pkb_type_id) {
                $byName->update(['pkb_type_id' => $pkbTypeId, 'code_system' => $codeSystem]);
            }
            return $byName;
        }

        return static::create([
            'pkb_type_id' => $pkbTypeId ?: null,
            'code_system' => $codeSystem ?: null,
            'name' => $name,
            'slug' => $slug,
            'default_unit' => $unit ?: null,
            'is_curated' => false,
        ]);
    }
}
