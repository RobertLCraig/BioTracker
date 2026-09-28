# A manual lab result with an order id joins no panel

## Why
`POST /api/v1/lab-results` accepts `lab_order_id` (`StoreLabResultRequest`), but
`LabResultController::store()` passes the data straight to `LabResult::create()`. `lab_order_id`
is not a `lab_results` column and not fillable, so it is dropped without an error. The result is
saved with `lab_panel_id` null.

`docs/DATA-MODEL.md` says `lab_panel_id` is null only "when a standalone manual result has no order
id". So a manual or pasted result that does carry an order id is stored as if it had none, and it
never groups with the rest of its panel. The paste flow submits its confirmed rows through this
same endpoint, so it loses the order id too. Found while card 0004 checked the dedup section of
`DATA-MODEL.md` against the code.

## Links

**Relates to**
- `0004` - it corrected `DATA-MODEL.md` to say the manual path writes no panel, which is this fault.

## Not this card
No dedup for manual entries, no change to the PKB import path, no frontend change.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN a manual result is posted with a `lab_order_id`, THE API SHALL attach it to that
      user's panel for the order, creating the panel if none exists.
      proves: `test_manual_result_with_order_id_joins_its_panel`
<!-- AC:END -->

## Tasks
- [ ] Add the test to `tests/Feature/LabResultImportTest.php`: post a result with a `lab_order_id`
      and assert `lab_panel_id` points at a panel whose `lab_order_id` matches.
- [ ] In `LabResultController::store()`, resolve the panel the way
      `LabResultImporter::resolvePanel()` does, keyed on `(user_id, client_id = lab_order_id)`.

## Comments

**2026-09-28** RESULT: done
TESTS: +1 new, all green (19 tests, 96 assertions)
TOUCHED: app/Http/Controllers/Api/V1/LabResultController.php
TOUCHED: tests/Feature/LabResultImportTest.php
TOUCHED: docs/DATA-MODEL.md
TOUCHED: docs/HANDOVER.md
TOUCHED: docs/board/in-progress/0008-manual-lab-result-drops-lab-order-id.md
TOUCHED: docs/board/todo/0009-lab-result-update-drops-lab-order-id.md
OUT-OF-SCOPE: 0009

Test first: `test_manual_result_with_order_id_joins_its_panel` failed with "lab_order_id was
dropped: the result joined no panel" before the fix. It covers both halves of #1: a new order
creates a panel, and a post to an imported order (`ORDER1`) joins the existing one.

`store()` now keys the panel on `(user_id, client_id = lab_order_id)` like `resolvePanel()`, but
with `firstOrCreate`, not `updateOrCreate`. Reason: `resolvePanel()` overwrites the panel's fields,
so a manual row would have wiped an imported panel's `lab_type`, `performing_org` and `source`. The
test asserts that they survive. A new panel gets `source = manual` and `collected_at = sampled_at`.
Paste rows go through the same endpoint, but the paste flow cannot tell them apart, so they are
stored as `manual`, not `pkb_paste`.

DATA-MODEL.md and HANDOVER.md said manual results write no panel; both now say what the code does.
Pint `--test` flags both touched PHP files, but the same fixers fire on the pre-change files, so
none come from this card. `vendor/bin/pest.bat` does not exist; the suite ran with phpunit.
No browser check: the frontend is untouched and out of scope.
