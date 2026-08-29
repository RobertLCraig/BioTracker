# Lab / Test Results — Feature Design

Status: **design-spec** (no code yet)
Author: design session 2026-07-17
Scope: adds a new "Lab / Test Results" domain to BioTracker. Built as "Phase 6" — see
[`../build/PROJECT_STATUS.md`](../build/PROJECT_STATUS.md). Its schema now lives in
[`../DATA-MODEL.md`](../DATA-MODEL.md) and its decisions in [`../DECISIONS.md`](../DECISIONS.md);
this doc keeps the feature's own reasoning, the PKB field map (§2.5) and the capture procedure
(§11).

---

## 1. Why & what

### Purpose
Let a user store **lab / diagnostic test results** — the blood and pathology results that
appear in an NHS patient portal such as Patients Know Best (PKB): e.g. HbA1c, serum
creatinine, total cholesterol, full blood count. Each result has a value, unit, reference
range, an out-of-range indicator, and the date the sample was taken.

### Why this is a new domain (not an extension of `VitalLog`)
`VitalLog` is a **fixed enum of six vitals** (weight, BP, temp, HR, blood sugar, SpO2),
each a single numeric value + unit. Lab results are fundamentally different:

| Aspect | VitalLog | Lab result |
|--------|----------|------------|
| Analyte set | 6, fixed enum | hundreds/thousands, open-ended (free text + code) |
| Value | always numeric | numeric **or** text ("Positive", "Not detected") |
| Reference range | none | per-result low/high, varies by lab/age/sex |
| Out-of-range flag | none | first-class (H / L / abnormal) |
| Grouping | none | many analytes share one order/panel + collection date + lab id |
| Status | none | preliminary / final / corrected / withdrawn |

So this is a new pair of tables, not a new `VitalType` case.

### Goals
- Store results faithfully to how a lab reports them (value, unit, range, flag, dates, source).
- Group results that came from one order/panel (as PKB groups by "Lab Id").
- Trend a single analyte over time (chart HbA1c across years) — reuse `AnalyticsService`.
- Three ingest paths: **manual entry**, **paste from PKB**, **file export import**.
- Same guarantees as every other BioTracker domain: per-user isolation (`UserOwnedScope`),
  encrypted free-text, audit-logged writes, dedup on import.

### Non-goals (this phase)
- No clinical interpretation / advice / normal-range calculation by us — we store the
  lab's own range and flag; we do not compute "healthy" ranges.
- No live pull from the NHS/PKB FHIR API (OAuth to NHS login) — designed-for but deferred.
  Ingest is user-initiated (type / paste / upload).
- No OCR of scanned-paper PDFs in v1 (structured CSV/FHIR first; PDF text extraction later).

---

## 2. Data model

**Three tables** (decisions D1, D2 resolved — see §8):
- `lab_test_definitions` — a **required** seeded catalog of known analytes (canonical name,
  unit, category). Results link to it for consistent naming/trending.
- `lab_panels` — one lab order / collection event. **Inferred**, not entered directly:
  upserted from the `lab_order_id` + `collected_at` + `source` carried by incoming results.
- `lab_results` — one analyte measurement. The atomic fact the user supplies.

The three tables field by field — every column with its type, nullability and units, plus the
`AbnormalFlag` and `LabResultStatus` enums and the rule that derives `abnormal_flag` — were
promoted to [`../DATA-MODEL.md`](../DATA-MODEL.md) by board card 0004. That file is their one home
now; what stays here is this feature's own reasoning.

### Matching an incoming analyte to a definition
**Matching order:** (1) exact `pkb_type_id`
from the JSON — reliable and unit-stable; (2) fallback to `slug`/`aliases` for manual/paste
entries with no PKB id. **No lossy drop:** an unmatched analyte auto-creates a row
(`is_curated = false`, `pkb_type_id` filled from the payload) so nothing is lost and the
next import of the same analyte reuses it. The lab's *as-reported* name/unit/range still
live on the result row — the catalog gives canonical grouping, it does not overwrite the lab.

### 2.5 PKB JSON → BioTracker field map (canonical)
Source: `GET /test/nodecorate_fetchTestHistoryJson.action?...&testResultTypeId={id}`.
Shape: `tests[].testHistoryMetadata[]` = one analyte (→ a `lab_test_definition` + many
`lab_results`); `.dataPoints[]` = one measurement in time (→ one `lab_result`). Panels are
grouped by `dataPoints[].labOrderId`.

| PKB JSON path | → | Column | Notes |
|---|---|---|---|
| `testHistoryMetadata[].testResultTypeId` | → | `lab_results.test_code`, def `pkb_type_id` | primary match key (LOINC map) |
| `testHistoryMetadata[].testResultType` | → | def `code_system` | e.g. `loincMapping` |
| `testHistoryMetadata[].name` | → | `lab_results.test_name` | as reported |
| `testHistoryMetadata[].unit` | → | `lab_results.unit` | may be `""` (e.g. ratios) |
| `testHistoryMetadata[].textualResultsOnly` | → | `lab_results.textual_only` | |
| `dataPoints[].id` | → | `lab_results.external_id` | **dedup key** |
| `dataPoints[].labOrderId` | → | `lab_panels.lab_order_id` | panel grouping |
| `dataPoints[].date.value` | → | `sampled_at` / panel `collected_at` | epoch ms → datetime |
| `dataPoints[].enteredDate.value` | → | `released_at` / panel `reported_at` | epoch ms |
| `dataPoints[].value.rawNumericValue` | → | `value_numeric` | |
| `dataPoints[].value.display` | → | `value_text` | e.g. "4.9 mmol/L" |
| `dataPoints[].value.comparator` | → | `value_comparator` | `<` / `>` / null |
| `dataPoints[].range.low` / `.high` | → | `range_low` / `range_high` | |
| `dataPoints[].range.textRange` (else `.display`) | → | `range_text` | non-numeric ranges |
| `dataPoints[].comment.comments` | → | `comment` (encrypted) | HTML, keep `<br>` |
| `dataPoints[].source.displayText` | → | `lab_panels.performing_org` | |
| `dataPoints[].source.via` | → | `lab_panels.source_via` | "Via Integration (HL7)" |
| `dataPoints[].labType` | → | `lab_panels.lab_type` | e.g. "BLS" |
| `dataPoints[].privacyFlags` | → | `privacy_flags` (json) | |
| `dataPoints[].delayedDisplayDate` | → | `available_from` | embargoed-until |
| `dataPoints[].deleted` / `.replaceDate` | → | `status` | → `withdrawn` / `corrected` |
| *(none — derived)* | → | `abnormal_flag` | value vs range, see [`../DATA-MODEL.md`](../DATA-MODEL.md) |

Worked row — from the captured Serum HDL cholesterol datapoint:
`id 3084750495`, `labOrderId 0026A652481`, `date 17 Jul 2026`, `value 0.8 mmol/L`,
`range 1–3` → `lab_result{ external_id:"3084750495", test_name:"Serum HDL cholesterol",
test_code:"943400139?…940", value_numeric:0.8, value_text:"0.8 mmol/L", range_low:1,
range_high:3, abnormal_flag:low (derived), sampled_at:2026-07-17 }`, attached to the inferred
`lab_panel{ lab_order_id:"0026A652481", collected_at:2026-07-17, lab_type:"BLS" }`.

---

## 3. Ingest paths

All paths normalise to the **same DTO** (`array` of panels each with result lines) and pass
through one importer, so dedup/validation/audit live in one place. Ordered by reliability:

```
PKB JSON  ──┐   (PRIMARY — real structured data, §3a)
manual form ┼─▶ LabResultDTO[] ─▶ LabResultImporter ─▶ dedup ─▶ persist ─▶ audit
PKB paste  ─┘        ▲
                     └── PkbTestJsonImporter (json)  /  LabResultParser (paste, fallback)
```

### 3a. PKB JSON import  ✅ PRIMARY PATH
The `nodecorate_fetchTestHistoryJson.action` payload (§2.5, real sample captured 2026-07-17)
is clean, LOINC-mapped, range-structured, and carries a stable per-datapoint `id` for dedup.
This is the canonical import.
- **See §11 for the exact, repeatable capture procedure** (one browser-console paste →
  downloads `pkb-tests.json` with every test). This is the step Rob does not want to redo
  the hard way.
- `POST /api/v1/lab-results/import` (upload the JSON, or an array of these payloads) →
  dispatches `ProcessLabImportJob` → `PkbTestJsonImporter` maps per §2.5 → dedup on PKB `id`.
- Idempotent: re-importing the same file changes nothing (same `external_id`s).

### 3b. Manual entry
- `POST /api/v1/lab-results` (single result; optional inline panel fields).
- `StoreLabResultRequest` validates: `test_name` required; `value_text` required;
  `unit`, range, `sampled_at` optional; enums validated.
- Standard CRUD (`index/show/update/destroy`) mirroring `VitalLogController`.

### 3c. Paste from PKB  ⚠ fallback, best-effort
- For when JSON capture isn't handy — paste the rendered "Latest" table text.
- `POST /api/v1/lab-results/parse` → `LabResultParser` (heuristic rows) → **preview, no
  persist**. User confirms → `/import`. Always shows what it understood before writing.
- Lossy vs JSON (no LOINC id, no per-datapoint id, comparator/range less reliable) — prefer 3a.

---

## 4. Dedup & idempotency
Follows the existing batch-import pattern (`client_id` columns added in
`2026_03_10_200000_add_client_id_to_log_tables`).
- **PKB JSON (primary):** each result's `external_id` = PKB datapoint `id` (globally unique,
  stable). A panel's `client_id` = the min `external_id` of its results. Re-importing the
  same file is a no-op; a result whose `status` advanced (`final → corrected`, or `deleted`)
  is updated in place.
- **Manual / paste (no PKB id):** synthesise
  `external_id = sha1(user_id | test_key | sampled_at | value_text | lab_order_id)`.
This makes re-import, paste-twice, and overlapping captures safe.

---

## 5. Trending & `test_key`
Charts need to group "the same test over time" despite naming/case/unit noise across labs.
**Resolved in build:** `test_key` = the **resolved `lab_test_definition`'s `slug`** (set at
both ingest points — importer and manual controller). This is stronger than slugging the raw
name: every analyte already matches one canonical definition (by PKB id, then name/alias), so
a manually-entered "Serum cholesterol" and a PKB-imported one share `test_key`
`serum-cholesterol` and trend together. (The model's `saving` hook keeps a
`test_code ?: slug(test_name)` fallback for any row created without a definition, which the
required FK should prevent.)
✅ Built: `GET /api/v1/lab-results/trends?test_key={slug}&from=&to=` returns the numeric
series + range band (decimals cast to real numbers for the chart client); with no `test_key`
it returns the list of available series for a picker. Non-numeric / `textual_only` results
carry `value: null` and are skipped by the chart.

---

## 6. API surface
| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/lab-results` | list; filters: `test_key`, `from`, `to`, `abnormal`, `panel`, `source` |
| POST | `/api/v1/lab-results` | manual create |
| GET | `/api/v1/lab-results/{id}` | show |
| PUT | `/api/v1/lab-results/{id}` | update |
| DELETE | `/api/v1/lab-results/{id}` | delete |
| GET | `/api/v1/lab-results/trends` | numeric series for one `test_key` |
| GET | `/api/v1/lab-panels` | list orders/panels with nested results |
| GET | `/api/v1/lab-panels/{id}` | one panel + its results |
| POST | `/api/v1/lab-results/parse` | paste → parsed preview (no write) |
| POST | `/api/v1/lab-results/import` | file upload **or** confirmed paste → queued import |

All under `auth:sanctum` + `EnsureTotpVerified`, like the rest of `/api/v1`.

---

## 7. Cross-cutting (must match existing conventions)
- `BelongsToUser` trait + `UserOwnedScope` on both models.
- Encrypt `notes` / `comment` via attribute get/set mutators, exactly like `VitalLog`.
- Audit-log create/update/delete/import/export via `AuditService`.
- API Resources (`LabResultResource`, `LabPanelResource`) — never return models raw.
- `source` column with the same vocabulary style as other logs.
- Reports: extend `ReportExportService` / the medical-report Blade to include a lab section.

---

## 8. Decisions (resolved 2026-07-17)

D1 to D4, each with the reasoning behind it, were promoted to
[`../DECISIONS.md`](../DECISIONS.md) by board card 0004 and live there now:

- **D1 ✅** — two tables, and the panel is inferred rather than entered.
- **D2 ✅** — the analyte catalog is required for v1, and an unmatched analyte auto-creates an
  uncurated row rather than being dropped.
- **D3 ✅** — PKB's `fetchTestHistoryJson` XHR is the primary import path (field map §2.5,
  repeatable capture procedure §11). Manual and paste remain fallbacks; a FHIR/CSV export and
  PDF parsing stay deferred.
- **D4 ✅** — paste is best-effort: preview, then confirm.

---

## 9. Build order (when approved) — provisional "Phase 6"
1. Enums (`AbnormalFlag`, `LabResultStatus`) + migrations (`lab_test_definitions`,
   `lab_panels`, `lab_results`).
2. `LabTestDefinition` model + `LabTestDefinitionSeeder` (starter set of common UK analytes,
   like `ActivityTypeSeeder`) + definition matcher (name/alias → slug, auto-create uncurated).
3. Models (`LabPanel`, `LabResult`) with trait + scope + encryption; resolve/attach
   definition + compute `test_key` + derive `abnormal_flag` on save; infer/upsert panel.
4. `StoreLabResultRequest`, resources, `LabResultController` + `LabPanelController` (CRUD).
5. `LabResultImporter` (shared) + dedup on `external_id`.
6. **`PkbTestJsonImporter` + `ProcessLabImportJob` + `POST /lab-results/import`** — maps the
   §2.5 shape; the primary path. Build against the captured `pkb-tests.json`.
7. `LabResultParser` (paste) + `/parse` (preview) + `/import` (confirmed paste) — fallback.
8. Trends endpoint via `AnalyticsService`; lab section in `ReportExportService`.
9. Tests + `../build/PROJECT_STATUS.md` Phase 6 entry + promote schema to `../DATA-MODEL.md`.

---

## 10. PKB endpoint reference
- **Per-test history (what we import):**
  `GET https://my.patientsknowbest.com/test/nodecorate_fetchTestHistoryJson.action`
  `?contextUserId={uid}&testResultTypeIdSource=loincMapping&testResultTypeId={typeId}`
  Returns that analyte's full history **plus its related panel** (e.g. asking for cholesterol
  returned all 6 lipids). GET, cookie-authenticated (session), no CSRF token needed.
- `{uid}` (`contextUserId`) and the CSRF token live in the page's
  `<script id="app-config-data">` JSON (`currentUserId`, `session.csrfToken`).
- Each result's `testResultTypeId` also appears in the "Trend" tab links
  (`/test/testHistory.action?...testResultTypeId=...`), which is how §11's script finds them.

---

## 11. HOW TO CAPTURE THE PKB JSON  (repeatable — do this before each import)

> Goal: end up with a single `pkb-tests.json` file containing every test, with near-zero
> manual work. Two methods — the console snippet is the low-effort one.

### Method A — one-paste console snippet (recommended)
1. Log in to PKB and open **Health → Tests**.
2. Click the **Trend** tab and choose the **All** date range (this makes every analyte's
   `testResultTypeId` link render on the page).
3. Open DevTools (**F12**) → **Console**. If it warns about pasting code, type `allow pasting`
   then Enter.
4. Paste the snippet below and press Enter. It reads your user id from the page, finds every
   test on the Trend view, fetches each history endpoint in your logged-in session, and
   downloads **`pkb-tests.json`**.
5. Send/keep that one file — it's the import input.

```js
// PKB → pkb-tests.json  (run on the Tests → Trend page, All range)
(async () => {
  const cfg = JSON.parse(document.getElementById('app-config-data').textContent);
  const uid = cfg.currentUserId;
  const tests = new Map(); // typeId -> source, deduped
  document.querySelectorAll('a[href*="testResultTypeId="]').forEach(a => {
    const u = new URL(a.getAttribute('href'), location.origin);
    const id = u.searchParams.get('testResultTypeId');
    const src = u.searchParams.get('testResultTypeIdSource') || 'loincMapping';
    if (id) tests.set(id, src);
  });
  if (!tests.size) { console.error('No tests found — are you on the Trend tab?'); return; }
  const out = [];
  for (const [id, src] of tests) {
    const url = `/test/nodecorate_fetchTestHistoryJson.action?contextUserId=${uid}`
              + `&testResultTypeIdSource=${src}&testResultTypeId=${id}`;
    try {
      const r = await fetch(url, { credentials: 'include' });
      out.push({ testResultTypeId: id, source: src, data: await r.json() });
    } catch (e) { out.push({ testResultTypeId: id, source: src, error: String(e) }); }
    console.log(`fetched ${out.length}/${tests.size}`);
  }
  const blob = new Blob([JSON.stringify(out, null, 2)], { type: 'application/json' });
  const a = Object.assign(document.createElement('a'),
    { href: URL.createObjectURL(blob), download: 'pkb-tests.json' });
  a.click();
  console.log(`Done — saved ${out.length} tests to pkb-tests.json`);
})();
```
Notes: it only reads your own data over your existing session (no credentials handled).
The importer (§3a) accepts exactly this `[{testResultTypeId, source, data}, …]` wrapper as
well as a bare single-test payload. If PKB changes its markup, the one line to adjust is the
`a[href*="testResultTypeId="]` selector.

### Method B — manual DevTools capture (fallback, no script)
1. DevTools (**F12**) → **Network** tab → filter box: type `fetchTestHistoryJson`.
2. On the Tests page, click **Trend** → **All**. Each test fires one request; they list under
   the filter.
3. Right-click any row → **Save all as HAR with content** → save the `.har`. It contains every
   response body. (Or per test: click a row → **Response** → copy — tedious, hence Method A.)
4. The importer can take either the HAR or extracted JSON.
