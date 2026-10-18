---
not_for_the_loop: raised by the Telegram bot; Rob decides whether it gets built
---
# Log medicines that are not on my medication list

## Why
Sometimes a person takes a medicine that is not saved in their medication list. They need a quick way to record the name, dose and time as it happens, without it being saved as a regular medicine.

Raised by the Telegram bot on 2026-10-17: Rob reported something it had no way to log.
The wording is the bot's, kept general because this board is public.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN Rob reports a medicine not on his list, THE BOT SHALL log it and add the medicine to his list (not as a regular one unless he says so). Test: `test_a_medicine_not_on_the_list_is_added_once_then_reused`.
<!-- AC:END -->
