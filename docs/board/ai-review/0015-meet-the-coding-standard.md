# Meet the coding standard

## Why
Rob wrote `C:\Dev\About-Me\Coding-Standard.md` on 2026-10-17 after the bot shipped with a token in
its logs, real data in a public repo, a Postgres-only bug and a lost reply. This card audits
BioTracker against it and fixes what fails.

## Audit, 2026-10-17
Passed already: debug off in production, `.env` mode 600, secrets only in `.env`, daily logs kept 14
days, systemd restarts the bot, the scheduler runs, Claude's output is validated by the API's own
rules, user rows are scoped, shell calls use argument arrays, every outbound call has a timeout.

Failed, fixed here:
- 53 known flaws in PHP packages and 13 in JavaScript packages. Updated; one passkey-library flaw is
  ignored with a reason in `composer.json` (the app never uses the affected class, the fix is a
  major version).
- No rate limit on login, TOTP, sign-up or passkey login. A 6-digit TOTP code could be guessed.
- Anyone on the home network or tailnet could sign up.
- A message that failed after the offset moved was lost and nobody was told.
- Nobody was told when the bot went offline (it sat on 401 after the token change).
- Telegram 429 and 5xx answers were not retried.
- New daily log files were created readable by everyone on the box.
- Deploy was steps typed by hand, and the Postgres suite was not part of it.
- No backup had ever been restored.

## Not this card
The home DNS that drops lookups (the EE Hub): home network work, and the retry covers it. A crash
loop before the first poll does not alert (systemd restarts it). Reformatting the whole repo.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 THE APP SHALL have no unignored advisory from `composer audit` or `npm audit`.
- [x] #2 THE API SHALL allow at most 10 requests a minute per address to the public auth routes.
      proves: `test_login_is_throttled_after_ten_tries_a_minute`
- [x] #3 WHILE `REGISTRATION_OPEN` is false, THE API SHALL refuse sign-up.
      proves: `test_sign_up_is_refused_when_registration_is_closed`
- [x] #4 WHEN a message fails after it was taken off the queue, THE BOT SHALL either deliver its
      reply later (if its entries were saved) or ask Rob once to resend it (if nothing was saved),
      never both, and SHALL keep what it owes across a restart.
      proves: `test_a_reply_that_fails_to_send_is_delivered_later_and_the_entry_saved_once`,
      `test_a_message_that_fails_before_saving_asks_for_a_resend`,
      `test_a_failure_half_way_through_saving_saves_nothing`
- [x] #5 WHEN polling has failed for 15 minutes, THE BOT SHALL send one ntfy push with no project
      name in it. proves: `test_fifteen_minutes_offline_pushes_to_the_phone_once`
- [x] #6 `bin/deploy.sh` SHALL deploy only committed code, only after the suite passes on SQLite
      and Postgres, and SHALL end by checking the web app and the bot.
- [x] #7 The nightly database dump SHALL have been restored once into a scratch database, with
      every row from before the dump present.
<!-- AC:END -->

## Tasks
- [x] Package updates, `composer.json` audit ignore with reason.
- [x] Throttle, registration switch, log permission, retry on 429/5xx.
- [x] `JournalBot::poll`: lost-message notice, ntfy push.
- [x] `bin/deploy.sh`.
- [x] Server `.env`: `REGISTRATION_OPEN=false`, `NTFY_TOPIC`, `SESSION_SECURE_COOKIE=true`.
- [x] Deploy with the script.
- [x] Restore test.
- [x] Handover, SecondBrain deploy line, ntfy users table.

## Comments
**2026-10-17**
RESULT: done
TESTS: +5 new, all green on SQLite and on Postgres (41 tests, 167 assertions)
TOUCHED: app/Services/Bot/JournalBot.php
TOUCHED: app/Console/Commands/TelegramPoll.php
TOUCHED: app/Http/Controllers/Api/V1/AuthController.php
TOUCHED: routes/api.php
TOUCHED: config/auth.php, config/logging.php, config/services.php, .env.example
TOUCHED: composer.json, composer.lock, package-lock.json
TOUCHED: bin/deploy.sh
TOUCHED: tests/Feature/AuthGuardsTest.php, tests/Feature/JournalBotTest.php

Each new test was run against the code without its fix first: all four failed. Live checks after
`bin/deploy.sh`: sign-up answered 403, the 11th login try in a minute answered 429, a test ntfy
push answered 200, the bot is active, `/up` answered 200. Restore: 31 tables; the only count
differences were rows created after the 02:45 dump (a lab import, today's logins).

### 2026-10-17 review (v20261017082000-6237)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 35s, run by this job rather than reported by the card.

**acceptance: sound**

I checked each criterion in the code. I found the code for all seven. I found no gap.

- #1: `composer.json`, `config.audit.ignore` ignores one advisory and gives a reason. I could not run `composer audit` or `npm audit`, because this session can only read files. The lock files were updated.
- #2: `routes/api.php`, the `throttle:10,1` group. It covers register, login, `login/totp`, `passkeys/challenge` and `passkeys/authenticate`.
- #3: `AuthController::register` stops with `abort_unless(config('auth.registration'), 403)`. `config/auth.php` reads `REGISTRATION_OPEN`.
- #4: `JournalBot::poll` counts each failed message in `$lost`. After the next `getUpdates` that works, it sends one notice and sets the count to zero. If the notice fails, the count stays, so it tries again at the next poll.
- #5: `JournalBot::poll` and `JournalBot::push`. After `ALERT_AFTER`, it sends one push. The `$alerted` flag stops a second push until polling works again. The title and the body do not use the project name.
- #6: `bin/deploy.sh`. It refuses a dirty tree, so only committed code ships (`git archive HEAD`). It runs the tests on SQLite and on Postgres before it copies anything, and `set -e` stops it if a step fails. At the end it checks `systemctl is-active telegram-bot` and `curl -f /up`.
- #7: this was a step done on the server, not code. The card comment records the restore.

VERDICT: sound

**scope: sound**

I checked this card for scope only. That means two things. Did it build things the card did not ask for? Did it leave any card work half done?

**What I checked**
- The card's own commit (`af170cf`) changes 15 files. All 15 are on the card's TOUCHED list.
- The branch diff against `master` is bigger. The extra files (`BotPullRequests`, `TelegramPollen`, the migration, the 0010 - 0013 cards) come from earlier cards that were already committed. This card did not add them.
- `routes/api.php`: the throttle (a request limit) wraps only the public auth routes, as the card says.
- `config/auth.php`: the `registration` switch reads `REGISTRATION_OPEN`.
- `config/logging.php`: daily logs now get mode `0640`.
- `JournalBot.php`: these parts are in the code: the lost-message count and notice in `poll`, the ntfy push with no project name, and the retry on 429/5xx in the Telegram call.
- `bin/deploy.sh`: it stops if there are uncommitted changes. It runs the tests on SQLite and on Postgres. It ends by checking the bot and `/up`.

**What I found**
- Nothing crosses the "Not this card" fence. There is no DNS work, no crash-loop alert and no repo-wide reformat.
- No task looks half done in the repo.
- I could not check the server `.env`, the SecondBrain deploy line or the ntfy users table from here, because they are outside this repo. They are also not acceptance criteria.

VERDICT: sound

**breakage: defect**

**What I found**

1. `JournalBot::poll` breaks a promise in its own docblock. The docblock says a failed message is not retried because "Claude may already have saved it". But the notice sent to Rob says "nothing from them was saved. Please send them again." `lost++` runs on any exception that comes out of `handleUpdate`. Two examples:
   - `save()` writes some rows, then a later entry throws.
   - Everything is saved, then the final `send()` fails after its retries.

   In both cases rows are already in the database. Rob is told they are not, so he sends the message again. Then the entries are logged twice. The new test does not build a failure after the save, so it cannot see this.

2. The lost count is held only in the process's memory. A crash and a systemd restart set it back to zero, so the notice is never sent. The card only scopes out a crash *before* the first poll, not this case.

3. Small issue, no criterion: `LoginView.vue` still shows a "Sign up" link and `RegisterView.vue` still posts to `/register`. With sign-up closed, users reach a form that always fails with 403.

UNMET: #4 the "send it again" notice says nothing was saved even when `handleUpdate` failed after `save()` wrote rows, so a resend logs those entries twice

VERDICT: defect

**acceptance**

- **#4 reopened**, by the breakage lens: the "send it again" notice says nothing was saved even when `handleUpdate` failed after `save()` wrote rows, so a resend logs those entries twice

**2026-10-17, after review**
RESULT: done
TESTS: #4 rewritten as 3 tests, all green (43 tests, 172 assertions)
TOUCHED: app/Services/Bot/JournalBot.php
TOUCHED: tests/Feature/JournalBotTest.php

Breakage #1 fixed: `save()` now runs in one `DB::transaction`, and nothing after it can throw (the
feature-request write and the reply are caught). So an exception out of `handleUpdate` means
nothing was saved, and only then is Rob asked to resend. A reply that fails to send is kept and
delivered as "(Delayed reply) ...". Removing the transaction makes the half-way test fail.
Breakage #2 fixed: what is owed is in the cache store (the database on the server), not in memory;
the delayed-reply test polls from a fresh instance to prove it.
Breakage #3 accepted: the sign-up form shows the server's 403 message, "Sign-up is closed on this
server." Hiding the link needs the server setting in the SPA build, which is built on the PC.

