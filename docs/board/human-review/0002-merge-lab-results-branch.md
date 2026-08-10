# Merge feat/lab-results into master now, or hold for the full capture?

## What I need from you
Pick one of the three options below. It decides whether master carries Phase 6 now, and whether
any schema change falling out of 0001 arrives as a second migration on master or as more work on
the branch. An agent cannot settle it: all three are safe, and the choice is about how you want
master to read rather than about evidence.

## Why
`feat/lab-results` is ahead of master by the Phase 6 commit plus the docs work, the suite is green
(9 passed), and nothing is pushed. Master has no lab results at all. 0001 could produce mapping or
schema changes, so the branch either waits for it or does not.

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
