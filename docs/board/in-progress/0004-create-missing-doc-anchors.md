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
- [ ] #1 WHEN a fresh session opens the repo, THE REPO SHALL auto-load a root `CLAUDE.md` whose
      first instruction is to read `docs/HANDOVER.md` before changing anything.
- [ ] #2 WHEN an agent needs the canonical data shape, THE REPO SHALL provide `docs/DATA-MODEL.md`
      carrying the three lab tables from the design doc §2 plus the pre-existing log tables, with
      one home per field rather than a copy in each doc.
- [ ] #3 WHEN an agent asks why something was built a given way, THE REPO SHALL provide
      `docs/DECISIONS.md` holding at least D1 to D4 from the design doc §8 and the architecture
      choices table from `docs/build/PROJECT_STATUS.md`, each with its reason.
- [ ] #4 WHEN an agent needs the goal and success criteria, THE REPO SHALL provide `docs/PRD.md`,
      and `docs/HANDOVER.md` SHALL link it in one line rather than restating it.
- [ ] #5 WHEN the doc set is complete, THE REPO SHALL have every relative markdown link resolve to
      a file that exists.
- [ ] #6 WHEN a reader consults `README.md`'s API reference, THE REPO SHALL list the ten lab
      endpoints from the design doc §6 alongside the other domains, which it currently omits.
<!-- AC:END -->

## Tasks
- [ ] Run `/scaffold-docs`, which creates only what is missing and never overwrites.
- [ ] Promote the design doc §2 tables into `docs/DATA-MODEL.md`; leave §2 in place as the
      feature's own design record and link the two, one direction only.
- [ ] Move D1 to D4 and the architecture table into `docs/DECISIONS.md`.
- [ ] Fill `docs/PRD.md` from `README.md`'s feature list and the design doc §1, marking anything
      that was never agreed as a gap.
- [ ] Add the lab endpoints to `README.md`'s API reference table.
- [ ] Update `docs/HANDOVER.md`'s "Goal & success criteria" and "Sibling docs" to point at them.
- [ ] Re-run the relative-link check over all tracked `*.md`.
