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
- [ ] #1 WHEN a lab result is updated with a `lab_order_id`, THE API SHALL attach it to that user's
      panel for the order, creating the panel if none exists.
      proves: `test_manual_update_with_order_id_moves_to_its_panel`
<!-- AC:END -->

## Tasks
- [ ] Add the test to `tests/Feature/LabResultImportTest.php`: create a result with no order id,
      `PUT` it with one, and assert `lab_panel_id` points at a panel whose `lab_order_id` matches.
- [ ] In `LabResultController::update()`, resolve the panel the way `store()` does (card 0008).

## Comments
