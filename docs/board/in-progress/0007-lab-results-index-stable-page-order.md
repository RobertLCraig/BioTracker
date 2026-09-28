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
- [ ] #1 WHEN results share one `sampled_at` across a page edge, THE API SHALL return each result on
      exactly one page. proves: `test_index_pages_tied_results_without_repeats_or_gaps`
<!-- AC:END -->

## Tasks
- [ ] Add a unique tie-break after `orderByDesc('sampled_at')` in
      `app/Http/Controllers/Api/V1/LabResultController.php` `index()`, e.g. `->orderByDesc('id')`.
- [ ] Add the test to `tests/Feature/LabResultImportTest.php`: seed over 50 results with one
      `sampled_at`, read every page, and assert the ids are unique and complete.

## Comments
