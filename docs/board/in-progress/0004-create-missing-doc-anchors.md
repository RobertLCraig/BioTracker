# Create the missing doc anchors (PRD, DATA-MODEL, DECISIONS, CLAUDE.md)

## Why
The repo has none of the four anchors the Project Doc Standard requires beyond the handover: no
root `CLAUDE.md` tripwire, no `docs/PRD.md`, no `docs/DATA-MODEL.md`, no `docs/DECISIONS.md`.
Without the tripwire the orient gate never fires for a fresh session, and without `DATA-MODEL.md`
the canonical shape lives only inside one feature's design doc. The design doc says so itself: its
step 9 is "promote schema to `../DATA-MODEL.md`", and that half never happened. The material to
fill all three already exists in the repo, so this is consolidation rather than authoring.

## Not this card
Not rewriting `README.md` beyond adding the ten lab endpoints its API reference is missing, not
touching the lab design doc beyond linking it, and not inventing requirements that were never
agreed. Where a source is missing, mark the gap loudly rather than filling it.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN a fresh session opens the repo, THE REPO SHALL auto-load a root `CLAUDE.md` whose
      first instruction is to read `docs/HANDOVER.md` before changing anything.
- [x] #2 WHEN an agent needs the canonical data shape, THE REPO SHALL provide `docs/DATA-MODEL.md`
      carrying the three lab tables from the design doc §2 plus the pre-existing log tables, with
      one home per field rather than a copy in each doc.
- [x] #3 WHEN an agent asks why something was built a given way, THE REPO SHALL provide
      `docs/DECISIONS.md` holding at least D1 to D4 from the design doc §8 and the architecture
      choices table from `docs/build/PROJECT_STATUS.md`, each with its reason.
- [x] #4 WHEN an agent needs the goal and success criteria, THE REPO SHALL provide `docs/PRD.md`,
      and `docs/HANDOVER.md` SHALL link it in one line rather than restating it.
- [x] #5 WHEN the doc set is complete, THE REPO SHALL have every relative markdown link resolve to
      a file that exists.
- [x] #6 WHEN a reader consults `README.md`'s API reference, THE REPO SHALL list the ten lab
      endpoints from the design doc §6 alongside the other domains, which it currently omits.
<!-- AC:END -->

## Tasks
- [x] Run `/scaffold-docs`, which creates only what is missing and never overwrites.
- [x] Promote the design doc §2 tables into `docs/DATA-MODEL.md`; leave §2 in place as the
      feature's own design record and link the two, one direction only.
- [x] Move D1 to D4 and the architecture table into `docs/DECISIONS.md`.
- [x] Fill `docs/PRD.md` from `README.md`'s feature list and the design doc §1, marking anything
      that was never agreed as a gap.
- [x] Add the lab endpoints to `README.md`'s API reference table.
- [x] Update `docs/HANDOVER.md`'s "Goal & success criteria" and "Sibling docs" to point at them.
- [x] Re-run the relative-link check over all tracked `*.md`.

## Comments

**2026-08-29** Built all four anchors and the two promotions.

Created: root `CLAUDE.md` (orient tripwire plus the build/test commands, including that the suite
is PHPUnit and that a bare Pint run reformats ~66 unrelated files), `docs/PRD.md`,
`docs/DATA-MODEL.md` and `docs/DECISIONS.md`.

`DATA-MODEL.md` carries the three lab tables and the seven log tables (`activity_types`,
`activity_logs`, `excretion_logs`, `medications`, `medication_logs`, `symptom_logs`, `vital_logs`),
plus the enums, `test_key`, the `abnormal_flag` derivation rule, the dedup keys, and the three open
divergences. The lab tables came from the design doc §2 but were checked against
`database/migrations/` and written to match what is actually built: the unique key on `lab_results`
is `(user_id, external_id)`, so dedup is per user, which §2 did not say.

**How I read "one home per field" (AC #2) against "leave §2 in place".** I moved the field tables,
the enum block and the derivation rule out of the design doc and left §2 with its own reasoning
(why three tables, how an incoming analyte is matched) plus one link to `../DATA-MODEL.md`. §8 and
the architecture table in `PROJECT_STATUS.md` were treated the same way: content moved, a named
summary and one link left behind. Links run one way only, from the feature docs up to the anchors.
If Rob wanted the tables left duplicated in §2, this is the edit to revert.

Assumed, and worth a look:
- The architecture choices have **no recorded date**. `DECISIONS.md` files them as "Undated
  (Phases 1 to 5)" and says the Phase 1 migrations are dated 2026-03-10, so they were settled by
  then. I did not invent a date.
- `PRD.md` is written after the fact from `README.md` and design §1, which is all the repo has. Its
  gaps are marked loudly: no numeric success targets were ever agreed, no production target is
  recorded, multi-user/sharing has never been discussed, and the README roadmap (food database,
  barcode, photo calorie estimation, meal plans) is recorded as a roadmap, not as requirements.
- Also updated in `HANDOVER.md` beyond the two sections the card names: the "Canonical data shape"
  and "Decisions locked" sections said those files were missing, which this card made false, and
  "What's next" still had 0004 at its head. Left everything else alone.

Link check (AC #5): 37 relative links across every tracked `*.md` plus the four new files. All
resolve except two in `docs/board/README.md`, and both are illustrative rather than real —
`../../DEPLOY.md` in an example the doc itself labels "shown as source, not as a live link", and
`../attachments/NNNN-YYYY-MM-DD-N.png`, a filename template. That file is owned by the `/handover`
skill and neither example can be made to resolve. Every link this card wrote resolves.

Not settled from the repository, left for a later card rather than widened into this one:
- `docs/spec/lab-results-design.md` still says "Status: **design-spec** (no code yet)", which has
  been false since Phase 6 shipped. Out of scope here.
- `database/migrations/2026_07_17_120200_create_lab_results_table.php` points at
  `docs/lab-results-design.md §2.5` in its docblock; the file moved to `docs/spec/` and the comment
  did not follow.
- The supporting tables (`users`, `audit_logs`, gamification, `daily_summaries`,
  `connected_services`, `passkey_credentials`, `media`) are still undocumented. `DATA-MODEL.md`
  names that as a gap; there is no card for it.

Checks: `.\vendor\bin\phpunit.bat` → OK, 10 tests, 60 assertions. There is no `vendor/bin/pest.bat`
in this repo, so the suite was run with PHPUnit. Pint was not run because this card changed no PHP;
every file it touched is markdown.
