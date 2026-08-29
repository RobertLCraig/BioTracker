# Merge feat/lab-results into master now, or hold for the full capture?

## What I need from you

**Pick 1, 2 or 3 below.** It decides whether master carries Phase 6 now, and whether a schema
change falling out of card 0001 arrives as a second migration on master or as more work on the
branch.

My recommendation is **2**: merge now, treat 0001's findings as follow-ups.

**Pass** is a number in this card. The merge is then ordinary work, and nothing about it needs you
a second time.

**Fail** is holding without saying so. Option 1 is a real choice and the branch is safe to leave;
what costs something is the branch ageing while nobody has decided it is waiting. Say "1" and it is
waiting on purpose.

**Why it needs you** All three are safe, so there is no evidence that settles it. The choice is
about how you want master's history to read, and option 3 stops being available the moment anything
is pushed.

## Why
`feat/lab-results` is ahead of master by the Phase 6 commit plus the docs work, the suite is green
(9 passed), and nothing is pushed. Master has no lab results at all. Card 0001 could produce
mapping or schema changes, so the branch either waits for it or does not.

## Links

**Relates to**
- `0001` - the full PKB capture, and the whole reason this is a question. It is the thing that
  could still change the lab schema, so it decides whether waiting buys anything. It is not a
  blocker: every option below is safe to take before it lands, which is why this card carries no
  `needs:`.

## Options
1. **Hold until 0001 verifies.** Cost: master stays without Phase 6 until the PKB capture happens,
   and the branch ages against any other work started meanwhile.
2. **Merge now, treat 0001's findings as follow-ups.** Cost: a schema change from the capture
   lands as a second migration on master instead of one tidy migration.
3. **Merge now, then amend before anything is pushed.** Cost: only safe while nothing is pushed;
   it becomes a history rewrite the moment the branch reaches the remote.

## Recommendation
Option 2. The feature is isolated behind three new tables and its own routes, and it touches only
two pre-existing files (`ReportController`, `ReportExportService`), so it cannot disturb the other
domains. Holding a finished, tested branch against a capture that needs your browser session is
what turns a one-commit branch into a merge conflict.

## Decided


**2026-08-16** Merge now, treat 0001's findings as follow-ups
