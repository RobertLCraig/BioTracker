# PRD: BioTracker

> A private, API-first personal health journal: everything one person tracks about their own
> health, in one place they own.

**Stage:** active
_Last updated: 2026-08-29_

> **How this doc was written.** No PRD was ever agreed for BioTracker; the project was built from
> `README.md` and, for Phase 6, from [spec/lab-results-design.md](spec/lab-results-design.md) §1.
> This file consolidates those two sources after the fact (board card 0004). Everything below is
> traceable to one of them or to the code. Anything that was never agreed is marked **GAP** rather
> than invented.

## Purpose
A person tracking their own health has the data scattered: food in one app, a fitness tracker in
another, blood results locked inside an NHS portal that has no export button. BioTracker is one
private place to record all of it, keep it encrypted, and get it back out again as JSON, CSV or a
PDF a clinician can read.

It is API-first because the same data should be reachable from a phone later without a second
back end.

## Goals
In priority order, as the build order shows them:

1. **Record every domain a person tracks** — food, activity, excretion, medications, symptoms,
   vitals, and lab / diagnostic test results.
2. **Keep it private** — per-user isolation, field-level encryption on free-text health data,
   audit logging on sensitive actions, optional TOTP MFA.
3. **Keep it portable** — full JSON export, PDF/CSV reports, and real account deletion.
4. **Take data in from elsewhere without duplicating it** — Fitbit, Apple Health, batch import,
   and PKB lab JSON, all idempotent on re-run.
5. **Show it back** — dashboard, trends per metric, and a single analyte trended over years.
6. **Stay API-first** — every feature is a versioned REST endpoint; the Vue SPA is one client of
   that API, not a separate application.
7. **Keep the habit going** — points, streaks and achievements on the five original log domains.

## Success criteria
Concrete and checkable. All of these currently hold:

- Every write is isolated per user by the `UserOwnedScope` global scope, so no query can return
  another user's row.
- Free-text health fields are encrypted at rest (`notes`, `symptom`, medication `name` and
  `prescribed_by`, lab `comment`, OAuth tokens).
- Sensitive actions are audit-logged through `AuditService`.
- A user can export everything as JSON, export a PDF or CSV report for any date range, and delete
  their account.
- Imports are idempotent: re-running the same import changes nothing.
- Lab results import from a captured PKB JSON file, group into the panel the lab ordered, derive
  their own out-of-range flag, and trend one analyte over time.
- The test suite passes.

**GAP: no numeric targets were ever agreed** — no response-time budget, no data volume, no uptime
or coverage figure. "It works and the suite is green" is the only bar the repo records.

## Scope
- Seven log domains: food and activity (`activity_logs`), excretion, medications and doses,
  symptoms, vitals, and lab results.
- Photo attachments on the log domains that take them.
- Gamification: points, streaks, ten achievements, user tasks.
- Analytics: daily summaries, dashboard, trend charts.
- Reports: PDF and CSV export, including a lab section.
- Integrations: Fitbit OAuth sync, Apple Health XML import, batch import for mobile clients.
- Security: Sanctum tokens, TOTP MFA, HTTPS enforcement, session timeout, audit log.
- A Vue 3 SPA over the same API.

## Non-goals
From [spec/lab-results-design.md](spec/lab-results-design.md) §1, for the lab domain:

- **No clinical interpretation or advice.** We store the lab's own reference range and derive an
  out-of-range flag from it; we never compute what is "healthy".
- **No live pull from the NHS / PKB FHIR API.** Ingest stays user-initiated: type, paste, upload.
- **No OCR of scanned-paper PDFs.** Structured JSON first.

Project-wide:

- Lab results deliberately sit outside the gamification path — no points for having blood taken.
- The `README.md` roadmap (food item database, barcode scanning, photo calorie estimation, meal
  plans) is a roadmap, not agreed requirements. **GAP:** none of it has been scoped or approved.

## Requirements
**Functional** — the API surface in `README.md` is the specification in practice: authentication
and TOTP, user profile and GDPR endpoints, CRUD plus batch import per log domain, the ten lab
endpoints, gamification reads, analytics, report export, and the integration endpoints.

**Non-functional**

- Every endpoint under `/api/v1` sits behind `auth:sanctum` plus `EnsureTotpVerified`.
- Any new model that holds user data uses the `BelongsToUser` trait and the `UserOwnedScope`
  global scope.
- Any new free-text health field is encrypted with `Crypt` get/set mutators.
- Any sensitive write is audit-logged.
- Any import path is idempotent on a dedup key.

## Constraints
- PHP 8.2+ on Laravel 12, run locally through Laravel Herd; PHP is not on `PATH`.
- SQLite and the `sync` queue driver in development.
- The data is one person's medical record, so it stays private and local by default. GDPR-style
  export and deletion are requirements, not extras.
- PKB has no export API, so lab ingest depends on a browser capture Rob runs himself
  ([spec/lab-results-design.md](spec/lab-results-design.md) §11) — board card 0001.

## Open questions
- [ ] **GAP — production target.** The repo only describes local development. Where, if anywhere,
      this is deployed, and what the production database and queue would be, is unrecorded.
- [ ] **GAP — is this ever multi-user?** Every table is per-user and the isolation scope is
      zero-trust, but no sharing, clinician access or invite feature has been discussed.
- [ ] **GAP — success metrics.** See above: no agreed numbers for performance, coverage or volume.
- [ ] Whether to merge `feat/lab-results` now or hold it for the full PKB capture — board card
      0002, which carries three options and a recommendation.
