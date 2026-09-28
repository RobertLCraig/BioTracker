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

### 2026-09-28 review (v20260928203606-e3f8)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 21s, run by this job rather than reported by the card.

**acceptance: sound**

I checked the one criterion against the code. I could not break it.

**#1 is met.**
- **The fix:** `LabResultController::index()` in `app/Http/Controllers/Api/V1/LabResultController.php` now sorts by `sampled_at`, then by `id`, both newest first. `id` is the primary key, so no two rows can tie. Each page now gets the same order, so no row can show on two pages or on none.
- **The filters:** the `test_key` filter and the other filters only add `where` clauses. They do not change the sort. `paginate(50)` runs after the sort.
- **The proof test:** `test_index_pages_tied_results_without_repeats_or_gaps` in `tests/Feature/LabResultImportTest.php` makes 120 results with the same `sampled_at` and reads pages 1 to 3. It checks three things:
  - The ids come back in exact descending order. This fails without the tie-break.
  - No id shows twice.
  - No id is missing.

**Limit:** the test runs on SQLite. SQLite cannot make the real fault happen. The builder said this openly. The order check still proves the fix gives a total order, and a total order is enough to meet the criterion.

The suite is green.

VERDICT: sound

**scope: sound**

**Scope review of card 0007: sound**

The long diff above covers commits from several cards. I looked only at commit `3ed6f9a`, which is this card's build commit. It changes 4 files, and the card names all 4.

**What it changed. Everything is inside the fence.**
- In `LabResultController::index`, the only new part is `->orderByDesc('id')`. The page size, the filters and the resource shape did not change.
- In `LabResultImportTest`, it adds one test: `test_index_pages_tied_results_without_repeats_or_gaps`. That is the test the card asked for. The test also checks the order of the ids. The builder said why: without that check, the test cannot fail on SQLite.
- There is no frontend change.
- In `docs/HANDOVER.md`, it changes three things: the "last updated" line, the known-bugs line and the expected test count. Each one records this card. The card comment declares all three.

**Half done: nothing from this card.** In `HANDOVER.md`, the "What's next" section is out of date. It still lists 0005 as in progress and 0006 and 0007 as todo. This card did not write that text, so I do not count it against the card. The next handover pass should fix it.

This finding disproves no criterion.

VERDICT: sound

**breakage: sound**

I tried to break the change and could not. I made no edits.

**The code fix holds.**
- `LabResultController::index` now sorts by `sampled_at`, then by `id`.
- `id` is unique, so every row has one fixed place in the list. No row can repeat on two pages, and no row can fall between them.
- Nothing else calls this sort. The filters still work the same, because they run before the page is cut.
- The Labs view in `resources/js/labs.js` (`loadResults`) still drops repeated ids. That step does no harm now.

**The new test is real.**
- `test_index_pages_tied_results_without_repeats_or_gaps` checks that the pages join into one list in descending `id` order.
- Without the fix, SQLite gives tied rows in ascending `id` order, so this check fails. This means the test can catch the bug.

**One small doc slip. It does not disprove the criterion.**
- `docs/HANDOVER.md` gives two different test counts.
- "Current state" says "17 passing".
- "How to pick up" says "18 passed".
- There are 18 tests (16 in `LabResultImportTest.php` and 2 example tests), so "17" is wrong.
- The builder edited that file for this card and fixed only one of the two lines. The next session should change "17" to "18".

I found no problem that breaks criterion #1.

VERDICT: sound

