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

### 2026-10-04 review (v20261004150153-8278)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 40s, run by this job rather than reported by the card.

**acceptance: sound**

The card makes one promise, #1. The code keeps it.

**#1 is met.**
- **Where it lives.** It is in `LabResultController::update()`. That function now sends the checked data through `LabResultController::withPanel()` before it calls `$labResult->update()`.
- **What `withPanel()` does.** When `lab_order_id` is filled, it calls `LabPanel::withoutGlobalScopes()->firstOrCreate()`. The lookup key is `user_id` (taken from the request) plus `client_id = lab_order_id`. It then puts the panel id into `lab_panel_id`.
- **"That user's panel."** The `user_id` in the key stops a match on another user's panel. This is true even with the global scope off.
- **"Creating the panel if none exists."** `firstOrCreate` makes a new panel when no panel matches.
- **The input reaches the code.** `StoreLabResultRequest` accepts `lab_order_id` as a nullable string, so `validated()` keeps it.
- **The test checks both halves.** `test_manual_update_with_order_id_moves_to_its_panel` tests two cases:
  - A `PUT` with a new order (`ORDER9`) makes a new panel for this user.
  - A `PUT` with an order that was already imported (`ORDER1`) joins that panel. The panel's `source` stays `pkb_json`.
- **The suite.** The script ran the suite itself, and it was green.

I tried to break #1 and I could not.

VERDICT: sound

**scope: defect**

I checked what the change did beyond what the card asks for.

**Over the fence, but harmless:**
- The card says "No change to `store()`". The builder still changed `LabResultController::store()`: the panel lookup moved out of it into a new private `withPanel()`. `store()` behaves the same, and `test_manual_result_with_order_id_joins_its_panel` is still green. The card's task says "resolve the panel the way `store()` does", so sharing one helper is a fair reading. I do not count this as a defect.
- The diff also moves cards 0004, 0005 and 0008 to new folders. Those moves come from other commits in the range, not from this build.

**Left half done (defect):**
- `docs/DATA-MODEL.md`, in "One shape, three ingest paths", still says: "`PUT` still drops `lab_order_id` (card 0009)". That is now false. The builder fixed the same note in `docs/HANDOVER.md`, but not here. `docs/HANDOVER.md` sends readers to `DATA-MODEL.md#dedup-keys`, so a reader is sent to a page that contradicts the code.

**What this means for criterion #1:**
- The stale line is in the docs, not the code. #1 is about API behaviour, so this finding disproves no criterion.
- The card will come back with #1 still ticked. The next session only has to delete or reword that one line in `DATA-MODEL.md`.

VERDICT: defect

**breakage: sound**

I tried to break card 0009. I could not.

**What holds:**
- **The import is there.** `LabResultController::withPanel()` takes an `Illuminate\Http\Request`. The controller already imports that class, so the call does not fail.
- **`update()` and `store()` use one key.** They both call `withPanel()`. The key is `(user_id, client_id = lab_order_id)`, which is the same key that `LabResultImporter::resolvePanel()` uses. Another user's panel cannot match, because the key names the user.
- **An imported panel keeps its data.** `firstOrCreate` does not write over a panel that already exists. The extended test checks this: `source` stays `pkb_json`.
- **Validation lets the field through.** `StoreLabResultRequest::rules()` allows `lab_order_id` as nullable. A null or empty value fails `filled()`, so the panel stays as it was.
- **The docs are true.** The `docs/HANDOVER.md` seam note now says create and update.

**Gap that disproves no criterion:** a `PUT` with `lab_order_id: null` does not take a result out of its panel. Criterion #1 asks only about an update that carries an order id, and the card's "Not this card" section leaves panel cleanup out. So this is not a defect on this card.

VERDICT: sound


**2026-10-04** The reviewer's acceptance lens returned this card sound: The card makes one promise, #1. The code keeps it. The reviewer's scope lens returned this card defect: I checked what the change did beyond what the card asks for. The reviewer's breakage lens returned this card sound: I tried to break card 0009. I could not. The loop moved it from todo/ to human-review/ because it has bounced 1 time between todo and ai-review, all 1 criteria ticked. THE BUILDER COULD NOT ACT ON THAT FINDING. A reviewer reopens every criterion it reports unmet, and the reviews that sent this card back named no criterion they disproved, so it came back with 1 of 1 criteria still ticked, every session found nothing open to do, and the loop promoted it again on the boxes. Add or reopen the criterion the finding breaks and move it back to todo/, or say here why the finding is wrong.

### 2026-10-04 manager (m20261004174001-24a2)

**outcome: rob**

The code does what the card asks, and the only finding is one out-of-date line in `docs/DATA-MODEL.md`, which no criterion covers. So a person must fix that line and close the card.

**what the session said**

I read the code and the docs. Here is what I found.

- **The code is good.** `LabResultController::update()` now sends its data through `LabResultController::withPanel()`. That function finds or makes the user's panel for the order. So criterion #1 is met. The acceptance and breakage reviews say the same.
- **The defect is real, but it is in the docs only.** `docs/DATA-MODEL.md` still says "`PUT` still drops `lab_order_id` (card 0009)". That is in the section "One shape, three ingest paths". It is now false.
- **The defect breaks no criterion.** The card has only one criterion, #1, and it is about how the API behaves. A wrong line in the docs does not disprove it. If I reopen the card, the builder will find nothing open, and the card will come straight back. That already happened one time.

What is left is a small job, not a question. Someone deletes or rewrites that one line, then accepts the card.

WHY: The code does what the card asks, and the only finding is one out-of-date line in `docs/DATA-MODEL.md`, which no criterion covers. So a person must fix that line and close the card.

OUTCOME: rob

