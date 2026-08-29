# HANDOVER: BioTracker

> Personal health journal: Laravel 12 API plus a Vue 3 SPA. A fresh session picking this up is
> finishing Phase 6, Lab / Test Results.

**Stage:** active
**Category:** site, app
**Status:** Phase 6 built, tested and green, but verified against one captured lipid panel only.
Its API and its SPA view are both on `master` now. The view has still never been opened in a
browser.
_Last updated: 2026-08-29 (card 0005 gave the analyte matcher its alias fallback)_

## Goal & success criteria
The goal, the success criteria and the non-goals are in [PRD.md](PRD.md).

## Canonical data shape
The canonical shape is [DATA-MODEL.md](DATA-MODEL.md): the three lab tables and the seven log
tables, field by field, plus the enums, the dedup keys, and the list of places a layer currently
diverges from it. The PKB JSON to column map stays in
[spec/lab-results-design.md](spec/lab-results-design.md) §2.5, which is the importer's own
reference. The supporting tables (gamification, analytics, integrations, auth) are still
undocumented — their shape is only in `database/migrations/`, and that gap is named in
`DATA-MODEL.md`.

The lab shape in one paragraph, so a session need not open either doc to orient:
`lab_test_definitions` is a shared, seeded analyte catalog (53 rows, not user-owned) that gives
canonical naming and grouping; `lab_panels` is one lab order, never entered by hand but inferred
and upserted from the `lab_order_id` carried by incoming results; `lab_results` is the atomic
measurement, holding the lab's own as-reported name, value, unit and reference range. The catalog
supplies grouping, it never overwrites what the lab reported. `test_key` is the resolved
definition's `slug` and is what makes an analyte trend as one series across manual and imported
entries. `abnormal_flag` is always **derived** from value against range, because PKB sends no flag
(its portal computes it client-side).

Two known divergences from that shape are still open, each a bug to close rather than a state to
preserve: only 6 of the 53 seeded definitions carry a `pkb_type_id`, and the design doc still calls
the importer `PkbTestJsonImporter` when the class is `PkbTestImportService`. Both are written up in
[DATA-MODEL.md](DATA-MODEL.md#known-divergences-to-close). The third, the dead `aliases` fallback,
is closed: card 0005 gave `resolveForImport()` an alias lookup between the slug match and the
auto-create, and seeded aliases on 50 of the 53 rows.

## Architecture / stack
Laravel 12 on PHP 8.2+ (Herd), SQLite in dev, queue driver `sync` in dev. API-first: every feature
is a versioned REST endpoint under `/api/v1`, behind `auth:sanctum` plus `EnsureTotpVerified`, and
the Vue 3 SPA (Vue Router, Pinia, Tailwind 4, Chart.js, built by Vite) is one client of that API
rather than a separate application.

Three conventions run through every domain and any new code must follow them: the `BelongsToUser`
trait plus the `UserOwnedScope` global scope for per-user isolation, encrypted attribute
get/set mutators on free-text health fields, and `AuditService` on sensitive writes. Observers on
the five original log models drive points, streaks and achievements; lab results deliberately sit
outside that gamification path.

## Key files / structure
```
app/Models/            one per domain; Lab* are the Phase 6 additions
  LabTestDefinition.php   the analyte catalog + resolveForImport() matcher
  LabPanel.php / LabResult.php   LabResult's saving hook derives test_key + abnormal_flag
app/Services/Lab/
  PkbTestImportService.php   PKB JSON -> rows, per design §2.5 (the primary path)
  LabResultImporter.php      shared persistence: panel upsert + dedup on external_id
  LabResultParser.php        best-effort paste parser, preview only, never persists
app/Http/Controllers/Api/V1/   LabResult / LabPanel / LabImport controllers
database/seeders/LabTestDefinitionSeeder.php   the 53 seeded analytes
routes/api.php         note lines 74-79: the specific lab routes are declared BEFORE the
                       apiResource, or /lab-results/trends is swallowed by {lab_result}
resources/js/views/    one view per domain, all registered in router/index.js
  LabsView.vue            card 0003: panel-grouped list, analyte trend chart, PKB upload
docs/spec/, docs/build/, docs/board/
```
The seams a fresh session should not re-derive: all three lab ingest paths normalise to one DTO
and pass through `LabResultImporter`, so dedup, validation and audit live in exactly one place;
dedup is on `external_id` (the PKB datapoint id, or a synthesised sha1 for manual/paste entries);
and the route ordering note above is a real trap, not a style preference.

Every decision and its reasoning is in [DECISIONS.md](DECISIONS.md), newest first: the feature
decisions D1 to D4 (the panel is inferred rather than entered, the analyte catalog is required,
PKB's `fetchTestHistoryJson` XHR is the primary import path, paste is best-effort
preview-then-confirm) and the project-wide choices (Sanctum, TOTP, `Crypt` field encryption,
`UserOwnedScope`, MediaLibrary, DomPDF, SQLite and `sync` in dev).

Locked this session: the doc set follows the canonical `docs/` layout with `spec/` and `build/`,
and work now lives on the board rather than in prose.

## Current state
- **Done:** Phases 1 to 5 are complete and shipped on master (auth and TOTP, the five log
  domains with photo uploads, gamification, analytics and report export, Fitbit and Apple Health
  and batch import). Phase 6 is on `master` too: three tables, three models, the
  matcher, the seeder, the PKB JSON importer, the queued import job, manual CRUD, the paste
  parser and its preview endpoint, the trends endpoint, a lab section in the CSV and PDF report,
  the SPA view, and its feature tests. The whole suite is green (15 passing). It has been
  verified end to end against one real captured lipid panel: 1 panel and 6 results created, re-import idempotent,
  out-of-range flags matching the portal, comments encrypting and decrypting, trends returning a
  numeric series, the PDF rendering its lab section.
- **In progress:** nothing half-built. The tree is clean and the branch is coherent.
- **Known bugs / broken:** none open. The known shortfalls are scope rather than defects: the demo
  seeder writing no lab data so `/labs` on the demo account only ever shows its empty state, and
  the labs list stopping at the API's 50-row page (it says so on screen, but does not page).

## What's next (in order)
Card **0005** (alias matching) is built on branch `card/0005` with all five criteria met and the
suite green; it is waiting on the scheduler's merge and review, not on more work. After it:
1. **0006** rewrite this board's cards for the reader.

The queue is [board/todo/](board/todo/), one card per file.

Card 0003 (the SPA Lab Results view) is built and its acceptance is ticked, but it has never been
opened in a browser — Herd serves the SPA from `C:\Dev\BioTracker`, not from the worktree it was
built in. Run `/run` against it before trusting it.

## Blockers / open questions
Two cards sit in [board/human-review/](board/human-review/) and both need Rob rather than an agent:
- **0001** the full PKB capture. It needs his logged-in portal session, so no agent can do it, and
  until it lands Phase 6 is verified against six results.
- **0003** the SPA Lab Results view, which needs the browser check no worktree can do.

**0002** (merge the lab-results branch or hold it for 0001) is still in [board/todo/](board/todo/),
but the branch it names no longer exists: Phase 6 and the SPA view are both on `master`. Read the
card before acting on it.

Neither of the two blocks 0005 or 0006, so a session with no answer to hand still has work.

## How to pick up
```bash
# PHP is not on PATH; Herd's is the one the tests were run with
PHP="/c/Users/r/.config/herd/bin/php84/php.exe"

"$PHP" artisan test                      # expect: 15 passed (75 assertions)
"$PHP" artisan migrate:fresh --seed      # rebuilds SQLite + seeds the 53 analytes
"$PHP" artisan route:list --path=lab     # expect: the 5 lab route groups from routes/api.php

ls docs/board/todo docs/board/in-progress docs/board/human-review   # the whole live picture
git log --format='%ad %s%n%b' --date=short -5                       # the narrative
```
Frontend: `npm run build`, or `composer run dev` to run Laravel, Vite, the queue and logs together.
Sign in with the seeded demo account named in `README.md`.

## Suggested skills / next tools
- `/handover resume` to start the next session: it reads this doc and the board, then picks up the
  head card without re-planning.
- `/run` when the SPA work in 0003 needs to be seen working rather than just tested.
- `/code-review` on the lab domain: nothing in Phase 6 has had an adversarial pass.
- `/checkpoint` to update the docs and commit mid-session without a full handover.
- ProgressBoard at `C:\Dev\ProgressBoard` renders this board with every other project's; do not
  build a second renderer.

## Sibling docs
| Doc | Purpose |
|-----|---------|
| [PRD.md](PRD.md) | Goal, success criteria, scope, non-goals, and the gaps that were never agreed |
| [DATA-MODEL.md](DATA-MODEL.md) | The canonical data shape: lab + log tables field by field, enums, dedup keys, open divergences |
| [DECISIONS.md](DECISIONS.md) | Every decision with its reason, newest first |
| [spec/lab-results-design.md](spec/lab-results-design.md) | Phase 6 design: why three tables, analyte matching, the PKB field map §2.5, and the repeatable capture procedure §11 |
| [build/PROJECT_STATUS.md](build/PROJECT_STATUS.md) | Phase 1 to 6 delivery checklist |
| [board/README.md](board/README.md) | The board convention (owned by the `/handover` skill, never edited here) |
| `README.md` (repo root) | Setup, demo account, full API reference |
| `CLAUDE.md` (repo root) | The orient tripwire a fresh session hits first, plus the build/test commands |

## Branch status
Card work happens on a `card/NNNN` branch in a worktree, and the scheduler merges it back into
`master`; `feat/lab-results` is gone and its work is on `master`. Nothing has been pushed:
`master` is the only branch tracking `origin`, and there is no PR.

## Session log
No prose log here by design. The narrative is the commit history, and the commit messages are
written to be read: `git log --format='%ad %s%n%b' --date=short`. Rationale belongs in
[DECISIONS.md](DECISIONS.md).
