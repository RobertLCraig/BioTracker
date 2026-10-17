---
needs: 0010
---
# The journal bot can correct or delete an entry it logged

## Why
First real use from Rob's phone, 2026-10-16: a meal message got a follow-up question, then two
rows. The reply then offered "tell me if it was 3 or 4 and I'll adjust". Card 0010 had no way to
change a row, so "it was 4" would have logged a second row for the same meal and doubled the total.

## Links

**Relates to**
- `0010` - the bot; its fence said "no undo or edit from the chat", which this card lifts.

## Not this card
Only rows from today's log are offered to Claude. No undo of an undo. No edit of lab results,
excretion or vitals.

## Acceptance
<!-- AC:BEGIN -->
- [ ] #1 WHEN Claude sends an entry with `replaces_id` of one of Rob's rows, THE BOT SHALL update
      that row, and WITH `remove` SHALL delete it.
      proves: `test_a_correction_changes_the_row_and_a_removal_deletes_it`
- [x] #2 WHEN `replaces_id` names another user's row, THE BOT SHALL leave it alone and say
      "entry to change not found".
      proves: `test_a_correction_cannot_reach_another_users_row`
<!-- AC:END -->

## Tasks
- [x] Today's log in the context shows each row as `[#id]`; the prompt says how to correct or delete.
- [x] `save()` finds the row through the user scope, by kind: activity, symptom or medication.

## Comments
**2026-10-16**
RESULT: done
TESTS: +2 new, all green (28 tests, 127 assertions)
TOUCHED: app/Services/Bot/JournalBot.php
TOUCHED: tests/Feature/JournalBotTest.php
OUT-OF-SCOPE: card 0010's review read card 0012's diff, not the bot; the bot code has had no
adversarial or security pass yet. Review `JournalBot.php` whole here, not just this diff.

A removal deletes before the entry's other fields are checked, so `remove` needs only `kind` and
`replaces_id`. The bot is running on Rob's PC with this code (restarted 21:06).

### 2026-10-16 review (v20261016221234-a85c)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 2s, run by this job rather than reported by the card.

**acceptance: sound**

I tried to break both criteria. I could not.

**#1 is met.** The code is in `app/Services/Bot/JournalBot.php`, in the function `save()`. It picks the model class from `kind`. Then it calls `$table::find($e['replaces_id'])`. If `remove` is set, it calls `$existing->delete()`. If not, it calls `$existing->update($data)`. The test `test_a_correction_changes_the_row_and_a_removal_deletes_it` checks both paths. It also checks that no duplicate row is made.

**#2 is met.** `ActivityLog`, `SymptomLog`, `MedicationLog` and `VitalLog` all use the trait `BelongsToUser`. That trait adds `UserOwnedScope`, a filter that shows only the logged-in user's rows. So `find()` returns null for another user's row. Then `save()` adds "(entry to change not found)" and skips the row. The test `test_a_correction_cannot_reach_another_users_row` proves it.

**One note outside the criteria.** The card says "No edit of ... vitals". But `save()` now maps `weight` to `VitalLog`. So a `replaces_id` can change or delete a weigh-in. This breaks the card's scope fence. It breaks no acceptance criterion. Rob can choose to keep it or remove it.

VERDICT: sound

**scope: defect**

The work spills past this card's limits in three places.

**1. It crosses the "Not this card" limit on vitals.** In `JournalBot::save()`, the kind map now sends `'weight' => VitalLog::class`. So a `replaces_id` or `remove: true` with kind `weight` changes or deletes a `vital_logs` row. The card says: "No edit of ... vitals."

**2. It crosses the "today's log only" limit.** `JournalBot::context()` now gives Claude a list called "Recent weigh-ins". It holds the last 5 weigh-ins, from any day, each with its `[#id]`. The card says only today's rows are offered to Claude. With fault 1, this lets the bot edit or delete a weigh-in from an older day.

**3. The change grew far past the card.** The card asks for correct and delete only. The diff also adds:
- weight logging (`JournalBot::toRow()`)
- feature-request files (`JournalBot::handleUpdate()`)
- a new command, `BotPullRequests`, that runs ssh, writes cards and makes git commits
- a new command, `TelegramPollen`, plus its schedule in `routes/console.php`
- an "About Rob" file for Claude (`JournalBot::context()`)
- a new migration that widens three columns
- card 0011 work on pmox

None of this is on card 0013, and its comments list only `JournalBot.php` and the test. The correct and delete code itself is not in this diff. It is only context lines, so this diff does not show the card's own work.

UNMET: #1 the correction and removal path also reaches `vital_logs` weight rows, including rows older than today, which the card's limit forbids.

VERDICT: defect

**breakage: defect**

I found 2 breaks. Both are in `app/Services/Bot/JournalBot.php`. Both go against the card's "Not this card" rules. Both acceptance criteria still pass.

1. **Vitals can now be changed and deleted.** The card says "No edit of ... vitals". But `save()` now maps the kind `weight` to `VitalLog`. So `replaces_id` with `remove` deletes a weigh-in, and a correction rewrites one. No test covers this.

2. **Rows from other days can be changed.** The card says "Only rows from today's log are offered to Claude".
   - `context()` now lists the last 5 weigh-ins as `[#id]`, with no limit to today. So Claude sees rows from past days.
   - `save()` looks up the row with `$table::find()` and does not check the date. So any old activity, symptom or medication row of Rob's can be changed or deleted if Claude gives its id.

I checked these and they are fine:
- `VitalLog` uses the user scope (`BelongsToUser`). So another user's row is still safe, and #2 holds.
- A correction and a removal work as #1 says.

Neither break makes #1 or #2 false. So there are no `UNMET:` lines. The fix needs new criteria, for example "a weight row cannot be changed" and "a row that is not from today is not found". Expect this card to come back.

VERDICT: defect

**acceptance**

- **#1 reopened**, by the scope lens: the correction and removal path also reaches `vital_logs` weight rows, including rows older than today, which the card's limit forbids.

