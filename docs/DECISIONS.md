# Decisions: BioTracker

Append-only log of decisions and their rationale, newest first. Do not rewrite history; supersede
an old entry with a new one that links back to it.

---

## 2026-08-29 — the doc set is the four anchors, and work lives on the board
**Decision:** BioTracker follows the canonical doc layout — `docs/PRD.md`, `docs/HANDOVER.md`,
`docs/DATA-MODEL.md` and this file at the `docs/` root, with `docs/spec/` and `docs/build/` beneath
them, and a root `CLAUDE.md` that makes a fresh session read the handover first. Open work is one
card per file under `docs/board/`, not prose in a status doc.
**Why:** before this, the canonical data shape lived inside one feature's design doc and rationale
lived in three places, so a fresh session had no reliable first read. The board folder a card sits
in is its state, which is one fact instead of two that can disagree.
**Status:** active

---

## 2026-07-17 — D1: two lab tables, and the panel is inferred
**Decision:** `lab_panels` + `lab_results` (plus the catalog, D2). The panel is **inferred**, never
entered: on import it is upserted from the `lab_order_id` + `collected_at` + `source` carried by
the incoming result rows. Results sharing an order id attach to one panel; a lone manual result
with no order id has `lab_panel_id = null`. The user only ever supplies *results*.
**Why:** a lab order is something the lab did, not something the user knows. Asking a person to
create a panel before typing a result would be data entry they cannot get right, and the grouping
key is already in every incoming row.
**Status:** active

## 2026-07-17 — D2: the analyte catalog is required for v1
**Decision:** `lab_test_definitions` exists and every result links to it via `test_definition_id`
(required). It is seeded like `activity_types`. An unmatched analyte auto-creates an
`is_curated = false` row rather than being dropped.
**Why:** trending needs "the same test over time" to survive naming, case and unit noise across
labs, which a raw name cannot do. Making the link required means every row can trend; making
unmatched analytes auto-create means nothing is ever lost to a missing catalog entry. The lab's
as-reported name, unit and range stay on the result row — the catalog supplies grouping only, it
never overwrites what the lab said.
**Status:** active

## 2026-07-17 — D3: PKB's `fetchTestHistoryJson` XHR is the primary import path
**Decision:** import from PKB's `nodecorate_fetchTestHistoryJson.action` payload. Manual entry and
paste stay as fallbacks. A true file export (FHIR/CSV) and PDF parsing are deferred.
**Why:** PKB has no export button and no data in its page HTML, but the Tests page fetches clean,
LOINC-mapped, range-structured JSON that carries a stable per-datapoint id — which gives free,
reliable deduplication. A real sample was captured 2026-07-17. The field map is
[spec/lab-results-design.md](spec/lab-results-design.md) §2.5 and the repeatable capture procedure
is its §11.
**Status:** active

## 2026-07-17 — D4: paste is best-effort, preview then confirm
**Decision:** `POST /lab-results/parse` returns what the parser understood and writes nothing. The
user confirms, and only then does `/import` persist.
**Why:** PKB serves results through JavaScript rather than in page HTML, so a pasted table is
heuristic text with no LOINC id, no per-datapoint id, and less reliable comparators and ranges. A
parser that guesses silently would write wrong health data; one that shows its work first cannot.
**Status:** active

---

## Undated (Phases 1 to 5) — project-wide architecture choices
No date was recorded for these. The Phase 1 migrations are dated 2026-03-10, so they were settled
by then. Promoted here from [build/PROJECT_STATUS.md](build/PROJECT_STATUS.md), which now points at
this file.

| Decision | Choice | Why |
|----------|--------|-----|
| Auth | Sanctum tokens | API-first and mobile-ready: the same endpoints serve the SPA and a future app |
| MFA | TOTP via Google2FA | Standard TOTP with recovery codes, no SMS dependency |
| Encryption | Laravel `Crypt` facade | Field-level AES-256 on free-text health data |
| Data isolation | `UserOwnedScope` global scope | Zero-trust per-user filtering, so no query can forget the `where` |
| Media | Spatie MediaLibrary | Photo uploads on logs without hand-rolling storage |
| PDF | DomPDF | Medical report export |
| DB (dev) | SQLite | Simple local setup; swap to MySQL/Postgres for production |
| Queue (dev) | `sync` | No queue daemon needed locally |

**Status:** active
