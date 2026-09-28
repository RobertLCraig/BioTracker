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

### 2026-09-28 review (v20260928204625-3afb)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 21s, run by this job rather than reported by the card.

**acceptance: sound**

I checked criterion #1 against the code. It holds.

**#1 is met.**
- **The code.** `LabResultController::store()` reads `lab_order_id`. Then it calls `LabPanel::withoutGlobalScopes()->firstOrCreate()` with the key `(user_id, client_id = lab_order_id)`. It puts the panel id into `lab_panel_id` before `LabResult::create()`. `lab_panel_id` is fillable in `LabResult`, so the value is saved.
- **"That user's panel."** The lookup key includes the user id from the request. Another user's panel with the same order id cannot match. The global scope is turned off, but the explicit `user_id` in the key does the same job.
- **"Creating the panel if none exists."** `firstOrCreate` does this. The new panel gets `lab_order_id`, `source = manual` and `collected_at`.
- **The test.** `test_manual_result_with_order_id_joins_its_panel` in `tests/Feature/LabResultImportTest.php` tests both halves:
  - A new order (`ORDER9`) makes a new panel. That panel has the correct `lab_order_id` and `user_id`.
  - A second post to an imported order (`ORDER1`) joins that existing panel. The panel keeps its import data.
- The suite is green.

I found nothing that breaks #1.

VERDICT: sound

**scope: sound**

I checked only commit `24afca1`, which is the build commit for card 0008. The rest of the diff you were given comes from cards 0004 to 0007.

**Nothing crosses the fence.**
- The code change is only in `LabResultController::store()`. It adds one guarded `firstOrCreate` on `LabPanel`.
- The PKB import path is unchanged. `LabResultImporter` is untouched.
- Nothing in the frontend changed.
- The builder did not add dedup for manual entries. `store()` still writes no `external_id`.
- The card said to resolve the panel "the way `resolvePanel()` does". The builder used `firstOrCreate` instead of `updateOrCreate`. This is a deviation, but it is inside scope. The builder gave the reason: `updateOrCreate` would overwrite an imported panel's fields. The test proves those fields survive.
- The doc edits in `docs/DATA-MODEL.md` ("Dedup keys", "One shape, three ingest paths") and `docs/HANDOVER.md` record this card's behaviour, and every statement in them is correct.

**Nothing is half done.**
- `update()` drops `lab_order_id` in the same way. The builder raised this as card 0009 and did not fix it quietly. `DATA-MODEL.md` points to 0009.
- The `README.md` row for `POST /lab-results` says "optional inline panel fields". That text is now closer to true than before this card.

This finding disproves no criterion.

VERDICT: sound

**breakage: sound**

I tried to break card 0008 and I could not. The suite was green before I started, and I made no edits.

**What holds:**
- **The panel is found or made for this user only.** `LabResultController::store()` drops the user filter, but the lookup itself names `user_id` and `client_id`. So it cannot reach another user's panel.
- **The panel write cannot fail.** `LabPanel`'s fillable list takes every field the new code writes. The `lab_panels` table allows null in all the other fields.
- **It agrees with the importer.** `LabResultImporter::resolvePanel()` uses the same key. The table's unique index on `(user_id, client_id)` stops a second panel for one order.
- **The docs now match the code.**
  - `docs/DATA-MODEL.md` "Dedup keys" and `docs/HANDOVER.md` say that a manual result joins its order's panel.
  - The README row for `POST /lab-results` ("optional inline panel fields") is now roughly true, because `lab_order_id` makes a panel.
  - The comment in `resources/js/labs.js` `groupByPanel` ("no lab_order_id carry no panel") is still true.
- **Known gap, already on the board:** `PUT` still drops `lab_order_id`. That is card 0009, and the card says so.

**Small note (does not disprove #1):** The header comment in `resources/js/labs.js` still says the index "orders by sampled_at alone". Card 0007 made that false, not this card.

No criterion is disproved.

VERDICT: sound

