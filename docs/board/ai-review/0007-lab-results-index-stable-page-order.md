# Lab results list pages in a fixed order

## Why
`GET /api/v1/lab-results` pages at 50 and sorts by `sampled_at` only
(`LabResultController::index`). Every result in one lab panel shares the same `sampled_at`, so
many rows tie. SQL gives tied rows no fixed order, so the database may order them differently for
`?page=1` and `?page=2`. A row can then show on both pages, or on neither.

The Labs view now walks every page. It drops a repeated row, but it cannot recover a skipped one:
that result is silently missing from the list. Nobody chose a sort on `sampled_at` alone. The index
was written before anything read past page 1.

## Links

**Relates to**
- `0003` - its Labs view is the client that reads every page, and its thread records the fault.

## Not this card
No change to the page size, the filters, or the resource shape. No frontend change.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN results share one `sampled_at` across a page edge, THE API SHALL return each result on
      exactly one page. proves: `test_index_pages_tied_results_without_repeats_or_gaps`
<!-- AC:END -->

## Tasks
- [x] Add a unique tie-break after `orderByDesc('sampled_at')` in
      `app/Http/Controllers/Api/V1/LabResultController.php` `index()`, e.g. `->orderByDesc('id')`.
- [x] Add the test to `tests/Feature/LabResultImportTest.php`: seed over 50 results with one
      `sampled_at`, read every page, and assert the ids are unique and complete.

## Comments
**2026-09-28** RESULT: done
TESTS: +1 new, all green
TOUCHED: app/Http/Controllers/Api/V1/LabResultController.php
TOUCHED: tests/Feature/LabResultImportTest.php
TOUCHED: docs/board/in-progress/0007-lab-results-index-stable-page-order.md
TOUCHED: docs/HANDOVER.md (the known-bugs line and the expected test count)
OUT-OF-SCOPE: none

`index()` now sorts `->orderByDesc('sampled_at')->orderByDesc('id')`. The test seeds 120 results
with one `sampled_at` and reads pages 1 to 3.

The harness cannot build the fault itself. SQLite (the test database) gives tied rows the same
order on every page, so the unique-and-complete assertions were green before the fix. I watched
that happen. To get a red, the test also asserts that the three pages concatenate to ids in
descending order: that is the total order the fix adds, and it failed before the fix (SQLite
returned tied rows id-ascending). The overlap and gap on a page edge is still only shown in
reasoning, not in a test. A database that reorders ties between queries (MySQL, Postgres) would be
needed to watch it happen.

Pint `--test` fails on both PHP files, but only with fixers that the pre-change files already
trip (`concat_space`, `unary_operator_spaces`, `not_operator_with_successor_space`). The new lines
add none. No browser check: this card has no frontend change.
