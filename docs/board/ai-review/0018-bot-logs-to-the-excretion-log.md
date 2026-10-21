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
- [x] #3 WHEN Rob messages again, THE BOT SHALL see today's excretion entries with their ids, so it can correct or delete them. Same test.
<!-- AC:END -->

## Notes
`StoreExcretionLogRequest`'s `requiredIf` closure reads the HTTP input, which is empty when the
bot validates, so the bot overrides the `consistency` rule with `required_if:type,poop`.
