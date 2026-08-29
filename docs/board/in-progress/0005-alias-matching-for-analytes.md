# Alias matching for analyte definitions (close the §2 divergence)

## Why
The canonical shape says the analyte match order is (1) `pkb_type_id`, then (2) a fallback to
`slug` **or `aliases`** (design doc §2, `lab_test_definitions`). The code does not do the second
half: `LabTestDefinition::resolveForImport()` matches on `slug` only and never reads `aliases`,
and `LabTestDefinitionSeeder` never writes an alias, so the column exists, is cast to `array`, and
is dead. 53 definitions are seeded and 6 carry a `pkb_type_id` (the lipids captured 2026-07-17),
so 47 analytes depend entirely on their name slugging identically to whatever PKB calls them. When
it does not match, a second uncurated definition is created and that analyte's history splits
across two `test_key`s, which is exactly what the catalog was added to prevent. It is a divergence
between the documented data shape and the code, not a preference.

## Not this card
Not changing the match order itself, not curating the whole catalog against the full capture
(that follows 0001), and not backfilling `pkb_type_id` by hand: the resolver already backfills it
onto a seeded row the first time an analyte arrives with an id.

## Plan
Buildable now, and not blocked on 0001. The matching logic and the obvious variants (for example
"Haemoglobin estimation" for `Hb`, "Creatinine" for "Serum creatinine") can go in immediately; the
capture only refines which further aliases are worth seeding, so it is an influence rather than a
blocker and carries no `needs:`. A merge pass over the definitions the capture splits belongs to
this card's second visit.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN an incoming analyte name matches no slug but matches an entry in a definition's
      `aliases`, THE APP SHALL resolve to that definition rather than creating a new one.
- [x] #2 WHEN matching by name or alias, THE APP SHALL ignore case and surrounding whitespace.
- [x] #3 WHEN a definition is resolved by alias and the payload carries a `pkb_type_id` the
      definition lacks, THE APP SHALL backfill that id, as it already does on a slug match.
- [x] #4 IF an analyte matches neither an id, a slug, nor an alias, THEN THE APP SHALL still
      create an uncurated definition rather than dropping the row.
- [x] #5 WHEN a manual entry and a PKB import name the same analyte differently but resolve to one
      definition, THE APP SHALL give them the same `test_key` so they trend as one series.
<!-- AC:END -->

## Tasks
- [x] Extend `resolveForImport()` with an alias lookup between the slug match and the auto-create.
- [x] Extend the seeder row shape to carry aliases and seed the obvious variants.
- [x] Feature test covering alias resolution, the case/whitespace rule, and the `pkb_type_id`
      backfill on an alias match.
- [x] Re-run `php artisan test`.

## Comments

**2026-08-29** Built the alias fallback. `LabTestDefinition::resolveForImport()` now tries
`static::matchAlias($slug)` when the slug lookup misses, and the backfill branch that already ran on
a slug match now runs on either, so #3 came for free rather than as a second code path. Both callers
(`LabResultController::store()` and `PkbTestImportService`) already set `test_key` to the resolved
definition's slug, so #5 needed no code, only the test that pins it.

Matching is slug-to-slug on both sides, which is what satisfies #2: `Str::slug()` folds case,
surrounding and inner whitespace, and punctuation, so the seeder lists a naming variant once and
does not need its punctuated and spaced spellings as separate entries. `matchAlias()` scans the
catalog in PHP rather than in SQL — `aliases` is a JSON column and the comparison is on the slug of
its entries, not their stored text, so no portable query does it. At 53 seeded rows that is one
small query; there is a `ponytail:` comment on the method naming the ceiling and the upgrade path
(an indexed alias table) if the catalog ever reaches a few hundred rows.

Seeder row shape changed from `[name, unit, category, pkb_type_id?]` to
`[name, unit, category, aliases?, pkb_type_id?]`, so the six lipid rows moved their id one position
right. 50 of the 53 definitions now carry aliases; the three that do not are the comment rows
(`EGFR comment 1`, `Comment for HbA1c`, `Faecal occult blood comment`), which have no naming
variants worth guessing at. The aliases seeded are the ordinary UK pathology variants — the
`Serum X` / `X` pairing, and the expansion of each abbreviation — and not a curation of the catalog
against a real capture, which the card puts out of scope and 0001 has to supply.

A sixth test guards a hazard the card does not name: two definitions claiming one alias would make
resolution depend on row order. It asserts every alias slug and every definition slug is unique
across the catalog, and it earned its place immediately — it caught `Non-HDL cholesterol` and
`Non HDL cholesterol` on the same row slugging identically, and that entry was dropped as redundant.

Assumed, because the repository does not say: that an alias is a full analyte name rather than a
fragment to match on, so matching is exact-after-slugging and never substring. Substring matching
would pull `Serum iron` into `Serum iron binding capacity`, which is a different analyte.

Two things I could not settle here. `.\vendor\bin\pint.bat --test tests/Feature/LabResultImportTest.php`
fails on `concat_space`, and it fails identically on that file at HEAD — it is the paste-parser
test's string concatenation from before this card, not anything added here, and per `CLAUDE.md`
reformatting untouched code is Rob's call. The two files this card actually rewrote,
`app/Models/LabTestDefinition.php` and `database/seeders/LabTestDefinitionSeeder.php`, both pass
Pint. And nothing here was seen in a browser: the worktree is not what Herd serves. It needs no
browser check that I can see — the change is behind the API and the SPA reads `test_key` as before —
but a run of `/run` against `C:\Dev\BioTracker` after the merge would confirm the labs list still
groups as it did.

Suite: 15 passed, 75 assertions (was 10 passed, 60 assertions).
