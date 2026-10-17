---
needs: 0010
not_for_the_loop: homelab work; needs Rob's BotFather token and a Claude login on the VM
---
# Run BioTracker and the Telegram bot on pmox

## Why
BioTracker runs only on Rob's PC, with its data in local SQLite. The bot (card 0010) can only save
while the PC is on. Rob wants to text it all day, so the app, its database and `telegram:poll`
move to pmox.

## Not this card
No GPU or local model: Claude was chosen over it in card 0010. No public URL: polling needs none.

## Acceptance
<!-- AC:BEGIN -->
- [ ] #1 WHEN Rob's PC is off, THE BOT SHALL still answer a message and save it in BioTracker on pmox.
- [x] #2 WHEN the VM restarts, `telegram:poll` SHALL start again on its own (systemd or Supervisor).
- [x] #3 Rob's existing data on the PC SHALL be on pmox, and the PC copy SHALL no longer be the one in use.
<!-- AC:END -->

## Tasks
- [x] Read `C:\Dev\SecondBrain\wiki\Home and Tech\Home Network Quick Start.md` and
      `Home Network - Adding a Service.md` first: they decide where it goes and how it is reached.
- [x] Rob: create the bot with @BotFather and put the token in 1Password vault `Claude`.
- [x] Install PHP, the app and the Claude Code CLI on the chosen VM; Rob runs `claude` once to log in.
- [x] Set the `TELEGRAM_*` values, message the bot once to learn the chat id, set `TELEGRAM_CHAT_ID`.
- [x] Copy the real data across.

## Comments
**2026-10-16**
RESULT: partial (#1 waits on Rob's first message to the pmox bot)
TESTS: +0 new; the suite (28) also passes on Postgres
TOUCHED: app/Services/Analytics/AnalyticsService.php
TOUCHED: ubuntu-web VM 102 and db LXC 105 (below); SecondBrain Home Network, PMOX NAS; Portal
OUT-OF-SCOPE: open sign-up (anyone at home or on Tailscale can register); the Postgres password is
not in 1Password yet

Where, per the house rules: Rob's own web project, so ubuntu-web VM 102 behind Caddy at
`https://biotracker.home.craig.ooo` (home and Tailscale only); the database is `biotracker` on the
shared Postgres in db LXC 105, which has nightly dumps. PHP 8.5 from apt, its own FPM pool running
as `rob`, `telegram-bot.service` (system unit, `Restart=always`, enabled), a scheduler line in
`/etc/cron.d/biotracker`, Claude Code from the native installer with Rob's subscription login.
VM 102 had no backup job, so `web-nightly` (04:00) was added; it takes uploaded photos.
The full write-up and the deploy steps are on SecondBrain `PMOX NAS`, "102 ubuntu-web".

Postgres: one raw SQL string quoted `"ml"` (an identifier on Postgres); now `'ml'`. The suite was
then run from the PC against `biotracker_test` and passed, and that database held 31 tables after,
so it really ran on Postgres.

#2: the process was killed with `kill -9` and systemd started a new one 10 s later; the unit is
`enabled`, so it starts on boot. The VM itself was not rebooted, because that takes every home
name down while it restarts.

#3: the PC database held only the demo seed ("Test User") and the two real rows Rob logged through
the bot tonight. Rob registered on pmox (user 1); the two rows were copied to him. The bot token
was blanked in the PC `.env`, so the PC cannot poll against the pmox bot.

Went wrong: the first `migrate --seed` ran the demo seeder into the new database; it was rebuilt
at once with only the reference seeders. Nothing real was in it.
