# HANDOVER: BioTracker

> Personal health journal: Laravel 12 API plus a Vue 3 SPA. A fresh session picking this up is
> continuing Phase 6, Lab / Test Results, on the `feat/lab-results` branch.

**Stage:** active
**Category:** site, app
**Status:** Phase 6 built, tested and green, but verified against one captured lipid panel only.
The SPA view exists on `card/0003` (built, suite green, never seen in a browser) and the branch is
unmerged.
_Last updated: 2026-08-29 (card 0003 built the SPA Lab Results view)_

## Goal & success criteria
**Gap: there is no `docs/PRD.md`.** Card 0004 owes it, and the summary below is the interim, not
the source of truth. The nearest thing to a spec is `README.md` (feature list, API reference) plus
[spec/lab-results-design.md](spec/lab-results-design.md) §1 for Phase 6 specifically.

Interim goal: one private place to record everything a person tracks about their own health
(food, activity, excretion, medications, symptoms, vitals, and now lab results), API-first so a
mobile client can use the same endpoints, with the data kept private and portable.

Interim success criteria, all currently met: every write is isolated per user by a global scope,
free-text health fields are encrypted at rest, sensitive actions are audit-logged, a user can
export everything as JSON or a PDF/CSV report and delete their account, and imports are
idempotent so re-running one changes nothing.

## Canonical data shape
**Gap: there is no `docs/DATA-MODEL.md`.** Today the shape lives in two places, which is one too
many: the lab domain is fully specified in [spec/lab-results-design.md](spec/lab-results-design.md)
§2 (three tables, field by field, with types, nullability and units) and §2.5 (the PKB JSON to
column map, the single reference for the importer). Every other domain has no written model at
all; its shape is only in `database/migrations/`. Card 0004 promotes both into `DATA-MODEL.md`.

The lab shape in one paragraph, so a session need not open the design doc to orient:
`lab_test_definitions` is a shared, seeded analyte catalog (53 rows, not user-owned) that gives
canonical naming and grouping; `lab_panels` is one lab order, never entered by hand but inferred
and upserted from the `lab_order_id` carried by incoming results; `lab_results` is the atomic
measurement, holding the lab's own as-reported name, value, unit and reference range. The catalog
supplies grouping, it never overwrites what the lab reported. `test_key` is the resolved
definition's `slug` and is what makes an analyte trend as one series across manual and imported
entries. `abnormal_flag` is always **derived** from value against range, because PKB sends no flag
(its portal computes it client-side).

Where the layers currently diverge from this shape, each one a bug to close rather than a state
to preserve:
- **`aliases` is designed and dead.** §2 makes it a fallback match key; the column exists and is
  cast, but `LabTestDefinition::resolveForImport()` matches on `slug` only and the seeder writes
  no aliases. Card 0005.
- **Only 6 of 53 seeded definitions carry a `pkb_type_id`** (the lipids captured 2026-07-17). The
  other 47 rely on their name slugging identically to PKB's. The resolver does backfill the id
  onto a seeded row on first import, so this self-heals for every analyte whose name matches.
- **The importer class is named `PkbTestImportService`**, not `PkbTestJsonImporter` as the design
  doc §3a and §9 call it. The code is right; the doc's name is stale.

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

## Decisions locked
Feature decisions D1 to D4, each with its reasoning, are in
[spec/lab-results-design.md](spec/lab-results-design.md) §8: the panel is inferred rather than
entered, the analyte catalog is required, PKB's `fetchTestHistoryJson` XHR is the primary import
path (§11 holds the repeatable capture procedure), and paste is best-effort preview-then-confirm.
The project-wide choices (Sanctum, TOTP, `Crypt` field encryption, `UserOwnedScope`, DomPDF,
SQLite and `sync` in dev) are tabled in [build/PROJECT_STATUS.md](build/PROJECT_STATUS.md).
Both move into `docs/DECISIONS.md` under card 0004.

Locked this session: the doc set follows the canonical `docs/` layout with `spec/` and `build/`,
and work now lives on the board rather than in prose.

## Current state
- **Done:** Phases 1 to 5 are complete and shipped on master (auth and TOTP, the five log
  domains with photo uploads, gamification, analytics and report export, Fitbit and Apple Health
  and batch import). Phase 6 exists on `feat/lab-results`: three tables, three models, the
  matcher, the seeder, the PKB JSON importer, the queued import job, manual CRUD, the paste
  parser and its preview endpoint, the trends endpoint, a lab section in the CSV and PDF report,
  and seven feature tests. The whole suite is green (9 passing). It has been verified end to end
  against one real captured lipid panel: 1 panel and 6 results created, re-import idempotent,
  out-of-range flags matching the portal, comments encrypting and decrypting, trends returning a
  numeric series, the PDF rendering its lab section.
- **In progress:** nothing half-built. The tree is clean and the branch is coherent.
- **Known bugs / broken:** none open. The known shortfalls are scope rather than defects: the dead
  `aliases` fallback (card 0005), the demo seeder writing no lab data so `/labs` on the demo
  account only ever shows its empty state, and the labs list stopping at the API's 50-row page
  (it says so on screen, but does not page).

## What's next (in order)
The queue is [board/todo/](board/todo/), one card per file. At its head:
1. **0004** create the missing doc anchors and promote the lab schema into `DATA-MODEL.md`.
2. **0005** alias matching, which closes the one live divergence in the data shape.

Card 0003 (the SPA Lab Results view) is built and its acceptance is ticked, but it has never been
opened in a browser — Herd serves the SPA from `C:\Dev\BioTracker`, not from the worktree it was
built in. Run `/run` against it before trusting it.

## Blockers / open questions
Two cards sit in [board/human-review/](board/human-review/) and both need Rob rather than an agent:
- **0001** the full PKB capture. It needs his logged-in portal session, so no agent can do it, and
  until it lands Phase 6 is verified against six results.
- **0002** whether to merge `feat/lab-results` now or hold it for 0001. The card carries three
  options and a recommendation.

Neither blocks 0003, 0004 or 0005, so a session with no answer to hand still has work.

## How to pick up
```bash
# PHP is not on PATH; Herd's is the one the tests were run with
PHP="/c/Users/r/.config/herd/bin/php84/php.exe"

"$PHP" artisan test                      # expect: 9 passed (46 assertions)
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
- `/scaffold-docs` is card 0004's whole first task.
- `/run` when the SPA work in 0003 needs to be seen working rather than just tested.
- `/code-review` before 0002 is answered, since nothing in Phase 6 has had an adversarial pass.
- `/checkpoint` to update the docs and commit mid-session without a full handover.
- ProgressBoard at `C:\Dev\ProgressBoard` renders this board with every other project's; do not
  build a second renderer.

## Sibling docs
| Doc | Purpose |
|-----|---------|
| [spec/lab-results-design.md](spec/lab-results-design.md) | Phase 6 design: data shape §2, PKB field map §2.5, decisions §8, and the repeatable capture procedure §11 |
| [build/PROJECT_STATUS.md](build/PROJECT_STATUS.md) | Phase 1 to 6 delivery checklist and the architecture choices table |
| [board/README.md](board/README.md) | The board convention (owned by the `/handover` skill, never edited here) |
| `README.md` (repo root) | Setup, demo account, full API reference |
| `docs/PRD.md`, `docs/DATA-MODEL.md`, `docs/DECISIONS.md`, root `CLAUDE.md` | **Missing.** Card 0004 |

## Branch status
On `feat/lab-results`, two commits ahead of `master` (Phase 6, plus this session's docs
consolidation), tree clean. No PR, and the branch has never been pushed: `master` is the only
branch tracking `origin`. Merging is card 0002.

## Session log
No prose log here by design. The narrative is the commit history, and the commit messages are
written to be read: `git log --format='%ad %s%n%b' --date=short`. Rationale belongs in
`docs/DECISIONS.md` once card 0004 creates it.
