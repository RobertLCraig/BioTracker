---
not_for_the_loop: raised in a review of the bot's chat; Rob chose to have the bot ask for the Bristol type
---
# Bot logs pees and poops to the excretion log

## Why
The app has an excretion log (`excretion_logs`, the `/excretion` page), but the bot had no kind
for it. A reported bowel movement became a symptom with no severity, failed validation, and the
reply still said "Logged".

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN Rob reports a pee or a poop, THE BOT SHALL save it to `excretion_logs` with type, Bristol type, blood, pain and urgency. Test: `test_a_poop_goes_to_the_excretion_log_and_needs_its_bristol_type`.
- [x] #2 WHEN a poop has no Bristol type, THE BOT SHALL ask "Which type on the Bristol scale, 1 to 7?" and save nothing; a poop sent without one is refused, not guessed. Same test.
- [ ] #3 WHEN Rob messages again, THE BOT SHALL see today's excretion entries with their ids, so it can correct or delete them. Same test.
<!-- AC:END -->

## Notes
`StoreExcretionLogRequest`'s `requiredIf` closure reads the HTTP input, which is empty when the
bot validates, so the bot overrides the `consistency` rule with `required_if:type,poop`.

## Comments

### 2026-10-21 review (v20261021140637-951a)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 10s, run by this job rather than reported by the card.

**acceptance: sound**

I checked each criterion against the code in `app/Services/Bot/JournalBot.php`. All three are met.

**#1. A pee or a poop is saved to `excretion_logs`.**
- The bot's list of kinds and its schema now include `excretion`.
- The excretion branch of the function that builds each entry's fields copies `excretion_type`, `consistency` (the Bristol type), `has_blood`, `blood_amount`, `pain_level`, `urgency`, `logged_at` and `notes` to `ExcretionLog`.
- The test reads the saved row back and checks its owner, type, Bristol type, blood and pain values.

**#2. A poop with no Bristol type is refused.**
- The bot's instructions to Claude tell it to ask "Which type on the Bristol scale, 1 to 7?" and to send no entries.
- The bot does not trust Claude to obey. It replaces the `consistency` rule with `required_if:type,poop` (required when the type is poop). This means a poop with no Bristol type fails the check and is not saved.
- The test checks that the row count stays at 1 and that the reply says "Not saved".

**#3. The next message shows today's excretion entries with their ids.**
- The function that lists today's entries adds a `[#id] ... excretion: ...` line for each one.
- The save loop also maps `excretion` to `ExcretionLog`, so a later message can correct or delete one of those entries.
- The test checks that the next message to Claude contains `excretion: poop, Bristol 4, no blood, pain 5`.

You do not need to do anything. The card can go to `done/`.

VERDICT: sound

**scope: sound**

I checked the change. It is one line in `docs/HANDOVER.md`. That line now says card 0018 is "deployed".

**Findings:**
- **Nothing extra was added.** The change touches only the handover status line. It adds no code and no new behaviour.
- **The line is true.** Card 0018 is in `docs/board/ai-review/`. The deploy happened after the green commit `514d671`, as the "deploy without asking" memory says.
- **Nothing is half done.** The code for the card is in the earlier commit `514d671` (`JournalBot.php`, the card file). This commit only records the deploy.
- **One small note, not a defect.** That earlier commit also refreshed `docs/board/README.md`. It was not part of this card. But its commit message says so openly, and it is not in this diff.

No criterion is disproved.

VERDICT: sound

**breakage: defect**

I tried to break the card 0018 work, and I did. Rob's bot can stop working because of one entry made on the `/excretion` page.

**Finding 1 (defect, it breaks every bot message):**
- Place: `app/Services/Bot/JournalBot.php`, function `context()`, in the new `ExcretionLog` loop.
- The code reads `$x->blood_amount->value` each time `has_blood` is true.
- But `StoreExcretionLogRequest::rules()` lets `blood_amount` be empty (`nullable`). The web page uses these rules.
- So the web page can save "blood: yes, amount: empty" for today.
- Then the code reads `->value` on null. PHP stops with an error.
- `context()` runs on every bot message. So while that entry is in today's log, every message fails. Rob cannot see or fix today's entries through the bot.
- The bot's own saves set `blood_amount` to `'none'`, and the test only makes a bot save. So no test makes this case.

**Finding 2 (smaller):**
- Place: same file, function `toRow()`, the `excretion` branch.
- If Rob says "there was blood" but gives no amount, the bot saves `blood_amount = 'none'`.
- The entry then says "blood: yes" and "amount: none" at the same time.

UNMET: #3 `context()` reads `blood_amount->value` with no null check, so a web entry with blood but no amount stops every bot message.

VERDICT: defect

**acceptance**

- **#3 reopened**, by the breakage lens: `context()` reads `blood_amount->value` with no null check, so a web entry with blood but no amount stops every bot message.

