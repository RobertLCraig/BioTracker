# Telegram journal bot: text or photo a meal, Claude logs it

## Why
Rob wants to log meals, drinks, symptoms and doses from his phone during the day, by text or by a
photo, and have BioTracker updated without opening the app. The bot should ask a follow-up when a
detail matters, and comment on progress (today's totals, streak, tasks) when asked.

Decided with Rob on 2026-10-16:
- **Telegram**, not WhatsApp: free, a token from @BotFather, no business account.
- **Claude through the Claude Code CLI** on his subscription, so no API key. The model is a
  setting, `CLAUDE_MODEL`, default `haiku`.
- **Not a local model.** One meal photo was run through all of them. qwen3-vl:4b (the only vision
  model that fits the pmox 3070 Ti) and gemma4:e4b misnamed one of the four foods and roughly
  doubled the protein; qwen took 128 s. Haiku, Sonnet and Opus named all four foods correctly, in
  12 to 19 s, and Rob judged Haiku the most correct.

## Links

**Relates to**
- `0011` - runs this on pmox, so the bot works when the PC is off.
- `0012` - found while building this: system activity types vanish through the relation.

## Not this card
No hosting (card 0011). No webhook: long polling needs no public URL. No undo or edit from the
chat; a wrong row is fixed in the app. No excretion or vitals entries yet. No WhatsApp.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN Rob's chat sends a meal as text or photo, THE BOT SHALL save it as an `activity_logs`
      row for the configured user, with calories and macros in `metadata`, the time stored in UTC.
      proves: `test_a_meal_is_logged_for_the_configured_user_in_utc`
- [x] #2 WHEN any other chat sends a message, THE BOT SHALL reply nothing and not call Claude.
      proves: `test_a_stranger_gets_silence_and_claude_is_never_called`
- [x] #3 WHEN Claude proposes an entry that fails the API's own rules or names another user's
      medication, THE BOT SHALL not save it and SHALL say so in the reply.
      proves: `test_another_users_medication_is_not_logged`
- [x] #4 WHEN Claude fails, THE BOT SHALL save nothing and tell Rob.
      proves: `test_a_claude_failure_saves_nothing_and_says_so`
- [x] #5 WHEN Rob sends a follow-up, THE BOT SHALL give Claude the conversation so far and today's
      log, so it can answer its own question and comment on progress.
      proves: `test_the_next_message_carries_the_conversation_and_todays_log`
<!-- AC:END -->

## Tasks
- [x] `JournalBot` service, `telegram:poll` command, settings in `config/services.php` and `.env.example`.
- [x] `tests/Feature/JournalBotTest.php`, Telegram and the Claude process faked.
- [x] One live run against the real CLI with the meal photo, database rolled back.

## Comments
**2026-10-16**
RESULT: done
TESTS: +5 new, all green (25 tests, 120 assertions)
TOUCHED: app/Services/Bot/JournalBot.php
TOUCHED: app/Console/Commands/TelegramPoll.php
TOUCHED: config/services.php
TOUCHED: .env.example
TOUCHED: tests/Feature/JournalBotTest.php
OUT-OF-SCOPE: card 0012 (system activity types vanish through the relation)

Claude runs with `--tools ""`: it cannot read files or run commands, it can only return JSON to a
`--json-schema`. Photos go in as a base64 image block over `--input-format stream-json`. The bot
writes every proposed row itself, through the same `rules()` as the API's form requests, as the
configured user (`Auth::setUser`, so `UserOwnedScope` and the observers behave as in a request).
Chat memory is the last 12 turns in the cache for one day, not a table.

Live run (real Haiku, Telegram faked, transaction rolled back): the meal photo became one food row
with calories and all three macros, in 10 s; a drink sent as text became a second row, after one
follow-up question; "how am I doing today?" gave the day's totals and named the active
medications not yet logged.

Never run against the real Telegram: no bot token exists yet.

### 2026-10-16 review (v20261016081025-38ae)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 32s, run by this job rather than reported by the card.

**acceptance: sound**

I could not break this card. Each of the five promises traces to real code in `app/Services/Bot/JournalBot.php`. Each one also has a test with the right name in `tests/Feature/JournalBotTest.php`.

The diff I was given is for card 0012, not card 0010. So I read the 0010 code directly.

- **#1 (a meal is saved):** `handleUpdate` signs in as the configured user with `Auth::setUser`. `toRow` puts the calories and macros in `metadata`. `save` turns the time into UTC with `setTimezone(config('app.timezone'))`, then writes the row.
- **#2 (strangers get silence):** `handleUpdate` returns with no reply when the chat id does not match. This happens before `ask` runs, so Claude is never called.
- **#3 (bad entries are refused):** `save` checks each entry with the API's own `rules()`. `toRow` looks up the medication with `Medication::find`, which only sees the signed-in user's rows, so another user's medication is rejected. Anything refused goes into the reply after "Not saved:".
- **#4 (Claude fails):** `ask` throws an error when Claude fails. `handleUpdate` catches it, saves nothing, and tells Rob.
- **#5 (follow-ups):** `ask` sends the stored chat history and the output of `context()`. That output has today's logs, medications, streak and tasks.

VERDICT: sound

**scope: sound**

I checked the diff against card 0010. Card 0010's own work is the Telegram journal bot. The diff does not contain that work. It is all card 0012's work: the `ActivityType` scope fix, its test, and the card move. Both cards share the branch `card/0010`.

**Findings**

- **The extra work is not hidden.** Card 0010 names card 0012 as out of scope. Card 0012 has its own board commits ("todo to in-progress", "in-progress to ai-review") and its own result comment. So this is a separate card that shares a branch.
- **Card 0010's fence still holds.** The fence says no hosting, no webhook, no undo or edit, no excretion or vitals entries, and no WhatsApp. Nothing in the diff crosses it.
- **The bot is not broken by the change.** In `app/Services/Bot/JournalBot.php`, the bot gets activity types through `ActivityType::forUser()`. The new scope keeps the `UserOwnedScope::class` key, so `forUser()` still removes it. Bot behaviour does not change.
- **Nothing is half done for card 0010.** All five criteria and all three tasks have code and named tests. The suite is green.
- **One small loose end on card 0012.** Its two `## Tasks` boxes are still unticked, but its result comment says both are done. That is card 0012's problem, not card 0010's.

No criterion of card 0010 is disproved.

**What you do now:** nothing on card 0010. If you want each branch to hold one card, give card 0012 its own branch before you merge.

VERDICT: sound

**breakage: sound**

I tried to break this change and could not. Nothing needs fixing.

**What I checked:**
- The new filter on activity types (`ActivityType::bootBelongsToUser`) keeps the old name, `UserOwnedScope`. So `ActivityType::scopeForUser` still turns it off and adds its own "built-in, or mine" rule. The bot's two lookups in `JournalBot` use that rule, so they work the same as before.
- `AppleHealthImportService` and `FitbitService` switch off all filters first. The change does not touch them.
- `ActivityTypeController` only has the list (`index`) and create (`store`) actions. There is no route to change or delete a type. So a user still cannot reach a built-in type to edit it.
- The shared `UserOwnedScope` did not change. Other models still see only the user's own rows.
- The comment "System types are visible to all users" is now true. Before this change, it was false.

**One note:** the code shown is the fix for card 0012, not the card 0010 bot work. It does not disprove any of the five 0010 criteria.

VERDICT: sound

