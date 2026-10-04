# Editing a lab result's order id does not change its panel

## Why
`PUT /api/v1/lab-results/{id}` validates with `StoreLabResultRequest`, which accepts `lab_order_id`.
`LabResultController::update()` passes the data to `$labResult->update()`. `lab_order_id` is not a
`lab_results` column, so it is dropped without an error. A result edited to carry an order id, or a
different one, stays in its old panel or in none.

Card 0008 fixed the same drop on `store()` only, because its acceptance names a posted result.

## Links

**Relates to**
- `0008` - it resolves the panel from `lab_order_id` in `store()`; `update()` should key the same way.

## Not this card
No change to `store()`, to the PKB import path, or to the frontend. No cleanup of panels left empty
by a move.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN a lab result is updated with a `lab_order_id`, THE API SHALL attach it to that user's
      panel for the order, creating the panel if none exists.
      proves: `test_manual_update_with_order_id_moves_to_its_panel`
<!-- AC:END -->

## Tasks
- [ ] Add the test to `tests/Feature/LabResultImportTest.php`: create a result with no order id,
      `PUT` it with one, and assert `lab_panel_id` points at a panel whose `lab_order_id` matches.
- [ ] In `LabResultController::update()`, resolve the panel the way `store()` does (card 0008).

## Comments
**2026-09-28**
RESULT: done
TESTS: +1 new, all green
TOUCHED: app/Http/Controllers/Api/V1/LabResultController.php
TOUCHED: tests/Feature/LabResultImportTest.php
TOUCHED: docs/HANDOVER.md (the seam note and the expected test count)
TOUCHED: docs/board/in-progress/0009-lab-result-update-drops-lab-order-id.md
OUT-OF-SCOPE: none

The new test failed first with "lab_order_id was dropped on update: the result joined no panel".
The panel lookup from `store()` moved into a private `withPanel()`, and `update()` now calls it too.
`store()` keeps the same behaviour (`test_manual_result_with_order_id_joins_its_panel` still green);
only its code moved, so there is one key for both paths. An update without `lab_order_id` leaves
the panel as it was, as before. Pint `--test` flags `concat_space` and `!` spacing in both files,
but the same flags are on the files before this change; the repo-wide reformat is Rob's call.
No browser check: this is an API change and Herd serves `C:\Dev\BioTracker`.

**2026-10-04** RESULT: done
TESTS: +0 new (1 extended), all green (20 tests, 105 assertions)
TOUCHED: tests/Feature/LabResultImportTest.php
OUT-OF-SCOPE: none

The fix was already on this branch from the earlier take (5fc1687): update() calls the shared withPanel() that store() uses. The superseded branch (53d2785) held the same code change, so no code was taken from it. Its test also covered the second half of #1, an order whose panel already exists, which the test on master did not. I added that half to test_manual_update_with_order_id_moves_to_its_panel: after the ORDER9 move, a PKB import makes ORDER1, a PUT with ORDER1 must land on that imported panel and leave its source as pkb_json.

Watched red twice. With update() back to $request->validated(), it failed with 'lab_order_id was dropped on update: the result joined no panel.' With withPanel() keyed to a wrong client_id, the new half failed at line 366 ('3 is identical to 2'). Both breaks reverted; the controller is unchanged from master.

Pint --test flags concat_space on the test file; the same flag is on the file before this change, and the new lines have no concatenation. The repo-wide reformat is Rob's call. No browser check: API-only change, and Herd serves C:\Dev\BioTracker, not this worktree.
