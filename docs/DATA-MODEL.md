# Data model: BioTracker

_Last updated: 2026-08-29_

The single source of truth for this project's data shape. Every layer (API request, model,
importer, report, SPA) conforms to this. Anywhere a layer diverges is a bug to close, not a state
to preserve — the open ones are listed under [Known divergences](#known-divergences-to-close).

The lab tables below were promoted here from [spec/lab-results-design.md](spec/lab-results-design.md)
§2, which now points at this file rather than repeating it. That design doc keeps what is
feature-specific: why three tables, the matching order, and the PKB JSON → column map (§2.5), which
is the importer's own reference.

---

## Entities

Every user-owned table carries `user_id` (FK `users`, `cascadeOnDelete`) and is filtered by the
`UserOwnedScope` global scope via the `BelongsToUser` trait. Every table carries Laravel
`timestamps` (`created_at`, `updated_at`). Fields marked **encrypted** use `Crypt` get/set
attribute mutators on the model, so the column holds ciphertext.

### Lab domain (Phase 6)

#### `lab_test_definitions` — seeded analyte catalog
Shared reference data. **Not** user-owned and has no `user_id`: seeded like `activity_types`, 53
rows. Results link to it so the same analyte trends as one series whatever the lab called it.

| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `pkb_type_id` | string | yes | PKB `testResultTypeId` (e.g. `943400139`) — **the primary match key**; unique |
| `code_system` | string | yes | e.g. `loincMapping` (PKB `testResultType`) |
| `code` | string | yes | LOINC / SNOMED code once mapped |
| `name` | string | no | canonical analyte name, e.g. "Serum creatinine" |
| `slug` | string | no | unique — fallback match key, and the value of `lab_results.test_key` |
| `aliases` | json | yes | naming variants ("Creatinine", "Creat") — matched on their slug when the incoming name matches no `slug`. Seeded on 50 of the 53 rows |
| `category` | string | yes | e.g. "Biochemistry", "Haematology", "Lipids" |
| `default_unit` | string | yes | expected unit, e.g. "µmol/L" |
| `default_range_low` | decimal(12,4) | yes | typical adult reference low (fallback only) |
| `default_range_high` | decimal(12,4) | yes | typical adult reference high (fallback only) |
| `is_curated` | boolean | no | default `false`. `true` = seeded/reviewed; `false` = auto-created on import |

Indexes: unique `pkb_type_id`, unique `slug`.

#### `lab_panels` — one lab order / collection event
**Inferred, never entered by hand:** upserted from the `lab_order_id` + `collected_at` + `source`
carried by incoming result rows.

| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `user_id` | FK users | no | `cascadeOnDelete` |
| `name` | string | yes | panel name if known, e.g. "U&E", "Full Blood Count" |
| `lab_order_id` | string | yes | PKB `labOrderId` (e.g. `0026A652481`) — the panel grouping key |
| `collected_at` | timestamp | yes | PKB `date.value` — sample date, what trending uses |
| `reported_at` | timestamp | yes | PKB `enteredDate.value` — when the lab released it |
| `source` | string | no | default `manual`; one of `manual`, `pkb_paste`, `pkb_json`, `file_import` |
| `performing_org` | string | yes | PKB `source.displayText` — lab / trust |
| `source_via` | string | yes | PKB `source.via`, e.g. "Via Integration (HL7)" |
| `lab_type` | string | yes | PKB `labType`, e.g. "BLS" |
| `notes` | text | yes | **encrypted** |
| `client_id` | string | yes | dedup key: the lowest `external_id` of its results, or a synthesised hash |

Indexes: `(user_id, collected_at)`, unique `(user_id, client_id)`.

#### `lab_results` — one analyte measurement
The atomic fact. This is the only lab row a user ever supplies.

| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `user_id` | FK users | no | denormalised for `UserOwnedScope` and direct queries |
| `lab_panel_id` | FK lab_panels | yes | null when a standalone manual result has no order id |
| `test_definition_id` | FK lab_test_definitions | no | required — matched by `pkb_type_id`, else `slug`, else auto-created |
| `external_id` | string | yes | PKB datapoint `id` (e.g. `3084750494`) — **the dedup key** |
| `test_name` | string | no | as reported by the lab (PKB `name` / `testResultTypeName`) |
| `test_code` | string | yes | PKB `testResultTypeId` + `testResultType` (LOINC mapping) |
| `test_key` | string | no | the resolved definition's `slug` — the trending key (see below) |
| `value_text` | string | no | PKB `value.display`: "4.9 mmol/L", "Positive", ">60 mL/min" |
| `value_numeric` | decimal(12,4) | yes | PKB `value.rawNumericValue` — what charts and flags use |
| `value_comparator` | string(3) | yes | PKB `value.comparator` — `<` or `>` |
| `unit` | string | yes | PKB `unit`: "mmol/L", "µmol/L", "%", "10*9/L", or empty for ratios |
| `range_low` | decimal(12,4) | yes | PKB `range.low` |
| `range_high` | decimal(12,4) | yes | PKB `range.high` |
| `range_text` | string | yes | PKB `range.textRange`, else `range.display` when non-numeric |
| `abnormal_flag` | string (enum) | no | `AbnormalFlag`, default `unknown` — always **derived** (see below) |
| `status` | string (enum) | no | `LabResultStatus`, default `final` |
| `textual_only` | boolean | no | default `false`. PKB `textualResultsOnly` — text analytes, excluded from trends |
| `sampled_at` | timestamp | yes | PKB `date.value`; falls back to the panel's `collected_at` |
| `released_at` | timestamp | yes | PKB `enteredDate.value` |
| `available_from` | timestamp | yes | PKB `delayedDisplayDate` — embargoed-until date |
| `privacy_flags` | json | yes | PKB `privacyFlags` (generalHealth / mental / sexual / socialCare) |
| `comment` | text | yes | **encrypted** — PKB `comment.comments`, HTML with `<br>` kept |

Indexes: `test_key`, `(user_id, test_key, sampled_at)`, unique `(user_id, external_id)`. Dedup is
therefore per user: the same PKB datapoint id imported by two users is two rows, as it should be.

### Log domain (Phases 2 to 5)

#### `activity_types` — the log category catalog
Seeded system rows plus per-user custom ones. The only log table where `user_id` is nullable: a
null means a system type shared by everybody.

| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `name` | string | no | "Food", "Drink", "Exercise", "Sleep", "Custom" as seeded |
| `slug` | string | no | unique |
| `icon` | string | yes | emoji |
| `points_per_log` | integer | no | default 5; what `ScoringService` awards |
| `is_system` | boolean | no | default `false`; `true` for the five seeded rows |
| `user_id` | FK users | yes | null = system type; set = one user's custom type |

**There is no `food_logs` table.** Food is an `activity_type` with slug `food`, so the SPA's Food
view writes `activity_logs` rows like every other activity.

#### `activity_logs`
| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `user_id` | FK users | no | |
| `activity_type_id` | FK activity_types | no | |
| `logged_at` | timestamp | no | |
| `duration_minutes` | integer | yes | |
| `quantity` | decimal(10,2) | yes | |
| `unit` | string | yes | |
| `calories` | integer | yes | |
| `notes` | text | yes | **encrypted** |
| `metadata` | json | yes | flexible key/value; **also holds this table's `client_id`** |

Indexes: `(user_id, logged_at)`, `(user_id, activity_type_id)`.

#### `excretion_logs`
| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `client_id` | string | yes | unique — batch-import dedup key |
| `user_id` | FK users | no | |
| `type` | string (enum) | no | `ExcretionType` |
| `size` | string (enum) | yes | `ExcretionSize` |
| `consistency` | tinyint | yes | Bristol scale 1 to 7; null for urine |
| `colour` | string | yes | |
| `has_blood` | boolean | no | default `false` |
| `blood_amount` | string (enum) | no | `BloodAmount`, default `none` |
| `urgency` | tinyint | yes | 1 to 5 |
| `pain_level` | tinyint | yes | 0 to 10 |
| `logged_at` | timestamp | no | |
| `notes` | text | yes | **encrypted** |

Indexes: `(user_id, logged_at)`, `(user_id, type)`.

#### `medications`
| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `user_id` | FK users | no | |
| `name` | string | no | **encrypted** |
| `dosage` | string | yes | |
| `unit` | string | yes | |
| `frequency` | string | yes | |
| `prescribed_by` | string | yes | **encrypted** |
| `notes` | text | yes | **encrypted** |
| `is_active` | boolean | no | default `true` |
| `reminder_times` | json | yes | times of day the SPA reminds on |

#### `medication_logs` — one dose taken
| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `client_id` | string | yes | unique — batch-import dedup key |
| `user_id` | FK users | no | |
| `medication_id` | FK medications | no | |
| `taken_at` | timestamp | no | |
| `dosage_taken` | string | yes | |
| `notes` | text | yes | **encrypted** |

Index: `(user_id, taken_at)`.

#### `symptom_logs`
| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `client_id` | string | yes | unique — batch-import dedup key |
| `user_id` | FK users | no | |
| `symptom` | string | no | **encrypted** |
| `severity` | tinyint | no | 1 to 10 |
| `body_area` | string | yes | |
| `logged_at` | timestamp | no | |
| `duration_minutes` | integer | yes | |
| `notes` | text | yes | **encrypted** |

Index: `(user_id, logged_at)`.

#### `vital_logs`
| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | bigint PK | no | |
| `client_id` | string | yes | unique — batch-import dedup key |
| `user_id` | FK users | no | |
| `type` | string (enum) | no | `VitalType`: weight, BP, temperature, heart rate, blood sugar, SpO₂ |
| `value` | decimal(10,2) | no | |
| `secondary_value` | string | yes | e.g. the diastolic half of a blood pressure |
| `unit` | string | no | |
| `logged_at` | timestamp | no | |
| `source` | string | no | default `manual`; also `fitbit`, `apple_health`, `health_connect` |
| `notes` | text | yes | **encrypted** |

Index: `(user_id, type, logged_at)`.

### Supporting tables — **gap, not written up here**
`users`, `audit_logs`, `user_points`, `user_streaks`, `achievements` (+ pivot), `user_tasks`,
`user_task_completions`, `daily_summaries`, `connected_services`, `passkey_credentials`, and
Spatie's `media`. Their shape lives only in `database/migrations/`. Card 0004 scoped this file to
the lab tables plus the log tables; documenting the rest is not yet a card.

---

## Canonical representation

### Enums (`app/Enums/`)
```php
enum AbnormalFlag: string {
    case Normal = 'normal';              // HL7 N — within range
    case High = 'high';                  // H
    case Low = 'low';                    // L
    case CriticalHigh = 'critical_high'; // HH
    case CriticalLow = 'critical_low';   // LL
    case Abnormal = 'abnormal';          // A — non-numeric abnormal
    case Unknown = 'unknown';            // no numeric value, or no range
}

enum LabResultStatus: string {
    case Preliminary = 'preliminary';
    case Final = 'final';
    case Corrected = 'corrected';        // PKB "Corrected" / replaceDate present
    case Withdrawn = 'withdrawn';        // PKB deleted flag / "withdrawn by … on …"
}
```
The other backed enums (`VitalType`, `ExcretionType`, `ExcretionSize`, `BloodAmount`, …) are in the
same folder; the columns above name the enum each one belongs to.

### `abnormal_flag` is always derived
PKB sends no flag — its portal computes "out of range" in the browser. `AbnormalFlag::derive()` is
the one rule: `value_numeric` below `range_low` → `low`, above `range_high` → `high`, inside →
`normal`, and no numeric value or no bound at all → `unknown`. It is stored rather than computed on
read, so a future source that does send its own HL7 flag can be honoured instead.

### `test_key` is what makes a series
`test_key` is the resolved `lab_test_definition`'s `slug`, written at both ingest points (importer
and manual controller). That is stronger than slugging the raw name: a hand-typed "Serum
cholesterol" and a PKB-imported one resolve to the same definition, so they share
`test_key` `serum-cholesterol` and trend as one line. The model's `saving` hook keeps a
`test_code ?: slug(test_name)` fallback for any row created without a definition, which the
required foreign key should prevent.

### Dedup keys
- **Lab, PKB JSON:** `lab_results.external_id` is PKB's datapoint id — globally unique and stable,
  so re-importing the same file is a no-op. A panel's `client_id` is the lowest `external_id` of
  its results.
- **Lab, manual or paste:** no PKB id exists, so `external_id` is synthesised as
  `sha1(user_id | test_key | sampled_at | value_text | lab_order_id)`.
- **The five log tables:** `client_id`, supplied by the client, unique per table.
  `activity_logs` has no `client_id` column — it keeps the value inside its `metadata` JSON.

### One shape, three ingest paths
The PKB JSON importer, the manual controller and the paste parser all normalise to the same DTO and
persist through `LabResultImporter`, so dedup, validation and audit happen in exactly one place.
The PKB JSON → column map is [spec/lab-results-design.md](spec/lab-results-design.md) §2.5.

---

## Known divergences (to close)

- ~~**`aliases` is designed and dead.**~~ Closed by card 0005: `resolveForImport()` falls back to
  an alias lookup between the slug match and the auto-create, matching slug against slug so case,
  spacing and punctuation do not matter, and the seeder writes aliases on 50 of the 53 rows. What
  remains is curation, not divergence — the seeded variants are the ordinary UK pathology
  spellings, not a list checked against a real capture. Card 0001 supplies that.
- **Only 6 of the 53 seeded definitions carry a `pkb_type_id`** — the lipids captured 2026-07-17.
  The other 47 rely on their name slugging identically to PKB's. The resolver backfills the id onto
  a seeded row on first import, so this self-heals for every analyte whose name matches.
- **The importer class is named `PkbTestImportService`**, not `PkbTestJsonImporter` as
  [spec/lab-results-design.md](spec/lab-results-design.md) §3a and §9 still call it. The code is
  right; the design doc's name is stale.
