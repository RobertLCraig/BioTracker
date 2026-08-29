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
- [ ] #1 WHEN an incoming analyte name matches no slug but matches an entry in a definition's
      `aliases`, THE APP SHALL resolve to that definition rather than creating a new one.
- [ ] #2 WHEN matching by name or alias, THE APP SHALL ignore case and surrounding whitespace.
- [ ] #3 WHEN a definition is resolved by alias and the payload carries a `pkb_type_id` the
      definition lacks, THE APP SHALL backfill that id, as it already does on a slug match.
- [ ] #4 IF an analyte matches neither an id, a slug, nor an alias, THEN THE APP SHALL still
      create an uncurated definition rather than dropping the row.
- [ ] #5 WHEN a manual entry and a PKB import name the same analyte differently but resolve to one
      definition, THE APP SHALL give them the same `test_key` so they trend as one series.
<!-- AC:END -->

## Tasks
- [ ] Extend `resolveForImport()` with an alias lookup between the slug match and the auto-create.
- [ ] Extend the seeder row shape to carry aliases and seed the obvious variants.
- [ ] Feature test covering alias resolution, the case/whitespace rule, and the `pkb_type_id`
      backfill on an alias match.
- [ ] Re-run `php artisan test`.
