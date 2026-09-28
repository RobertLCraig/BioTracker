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
        // Slugging folds case and surrounding whitespace away on both sides of the
        // comparison, so "  serum CHOLESTEROL " and "Serum cholesterol" match.
        $slug = Str::slug($name) ?: 'unknown';

        if ($pkbTypeId !== null && $pkbTypeId !== '') {
            $byId = static::where('pkb_type_id', $pkbTypeId)->first();
            if ($byId?->is_curated) {
                return $byId;
            }

            // An auto-created row holding the id was left by an import before the alias
            // was seeded. Hand the id to the curated definition the name now reaches, so
            // imports and manual entries share one series.
            $curated = $byId ? static::matchCurated($slug) : null;
            if ($curated && ! $curated->pkb_type_id) {
                $byId->update(['pkb_type_id' => null]);
                $curated->update(['pkb_type_id' => $pkbTypeId, 'code_system' => $codeSystem]);

                return $curated;
            }
            if ($byId) {
                return $byId;
            }
        }

        // A curated alias outranks an auto-created row with the same slug: that row is
        // what an unmatched name left behind before the alias was seeded.
        $byName = static::matchCurated($slug) ?? static::where('slug', $slug)->first();
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

    /** The curated definition $slug names, by its own slug or else by an alias. */
    private static function matchCurated(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_curated', true)->first() ?? static::matchAlias($slug);
    }

    /**
     * First definition whose `aliases` hold a name that slugs to $slug.
     *
     * ponytail: scans the catalog in PHP because `aliases` is a JSON column and the
     * comparison is on the slug, not the stored text — no portable SQL does that. The
     * catalog is seeded reference data (52 rows), so this is one small query. If it ever
     * grows past a few hundred rows, store the slugged alias in its own indexed table.
     */
    private static function matchAlias(string $slug): ?self
    {
        return static::whereNotNull('aliases')->get()->first(
            fn (self $definition) => in_array(
                $slug,
                array_map(fn ($alias) => Str::slug((string) $alias), $definition->aliases ?? []),
                true,
            ),
        );
    }
}
