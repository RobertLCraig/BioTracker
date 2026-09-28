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
- [ ] #1 WHEN a manual result is posted with a `lab_order_id`, THE API SHALL attach it to that
      user's panel for the order, creating the panel if none exists.
      proves: `test_manual_result_with_order_id_joins_its_panel`
<!-- AC:END -->

## Tasks
- [ ] Add the test to `tests/Feature/LabResultImportTest.php`: post a result with a `lab_order_id`
      and assert `lab_panel_id` points at a panel whose `lab_order_id` matches.
- [ ] In `LabResultController::store()`, resolve the panel the way
      `LabResultImporter::resolvePanel()` does, keyed on `(user_id, client_id = lab_order_id)`.

## Comments
