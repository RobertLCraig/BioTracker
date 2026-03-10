# BioTracker — Project Status

Last updated: 2026-03-10T20:30:00Z
Current phase: Phase 5 — Integrations (COMPLETE)
Current step: ALL PHASES COMPLETE

## Overview

BioTracker is a personal health journal web application built with Laravel 12 (PHP 8.2+).
It is API-first, secured with Sanctum + TOTP MFA, GDPR/HIPAA-compliant, and designed
for future mobile app integration.

- **Repo:** https://github.com/RobertLCraig/BioTracker
- **Stack:** PHP 8.2, Laravel 12, SQLite (dev), Sanctum, Spatie MediaLibrary, Google2FA
- **Branch:** master (main branch: main)

---

## Phase 1 — Foundation & Security

- [x] 1.1 Scaffold Laravel 11 project (`chore: scaffold Laravel 11 project`)
- [x] 1.2 Install core packages (`chore: install core packages`)
- [x] 1.3 Create `config/biotracker.php` (`feat: add biotracker config`)
- [x] 1.4 Add MFA + consent columns to users table (`feat: add TOTP MFA to user model`)
- [x] 1.5 Create `audit_logs` table + `AuditLog` model + `AuditService` (`feat: add audit logging`)
- [x] 1.6 Create `UserOwnedScope` global scope + `BelongsToUser` trait (`feat: add user data isolation scope`)
- [x] 1.7 Create security middleware: `ForceHttps`, `SessionTimeout`, `EnsureTotpVerified` (`feat: add security middleware`)
- [x] 1.8 Create `AuthController` — register, login, TOTP, logout (`feat: implement auth + MFA endpoints`)
- [x] 1.9 Create `UserController` — profile, export, delete account (`feat: implement GDPR user endpoints`)
- [x] 1.10 Create `PROJECT_STATUS.md` (`docs: add project status tracker`)
- [x] 1.11 Create all PHP Enums under `app/Enums/` (`feat: add application enums`)

**Phase 1 complete.**

---

## Phase 2 — Core Logging

- [x] 2.1 Activity types — migration, model, `ActivityTypeSeeder`
- [x] 2.2 Activity logs — migration, model, `ActivityLogObserver` (stub)
- [x] 2.3 Activity controllers — `ActivityTypeController`, `ActivityLogController`, form requests, resources
- [x] 2.4 Excretion logs — migration + model
- [x] 2.5 Excretion controller — `ExcretionLogController`, form request, resource
- [x] 2.6 Medications — `medications` + `medication_logs` migrations + models
- [x] 2.7 Medication controllers — `MedicationController`, `MedicationLogController`, form requests, resources
- [x] 2.8 Symptom logs — migration + model
- [x] 2.9 Symptom controller — `SymptomLogController`, form request, resource
- [x] 2.10 Vital logs — migration + model
- [x] 2.11 Vital controller — `VitalLogController`, form request, resource
- [x] 2.12 Spatie Media Library — configure collections + conversions on all HasMedia models
- [x] 2.13 API routes — register all `/api/v1/` routes in `routes/api.php` (44 routes total)
- [x] 2.14 API Resources — all transformer classes

**Phase 2 complete.**

---

## Phase 3 — Gamification

- [x] 3.1 Points ledger — `user_points` migration + `UserPoint` model
- [x] 3.2 Streak tracking — `user_streaks` migration + `UserStreak` model
- [x] 3.3 `ScoringService` — award points, photo bonus, streak bonus
- [x] 3.4 `StreakService` — record activity, check milestones
- [x] 3.5 Wire observers — all 5 log observers call scoring + streak + achievement services
- [x] 3.6 Achievements — migrations, models, `AchievementSeeder` (10 achievements)
- [x] 3.7 `AchievementService` — check + award achievements, condition evaluators
- [x] 3.8 User tasks — `user_tasks` + `user_task_completions` migrations + models
- [x] 3.9 Gamification controllers — `PointController`, `StreakController`, `AchievementController`, `UserTaskController`

**Phase 3 complete.**

---

## Phase 4 — Analytics & Reports

- [x] 4.1 Daily summaries — migration + model
- [x] 4.2 `AnalyticsService` — dashboard, trends, regenerateDailySummary
- [x] 4.3 `AnalyticsController` — dashboard + trends endpoints
- [x] 4.4 `ReportExportService` — PDF + CSV; Blade template `reports/medical-report.blade.php`
- [x] 4.5 `ReportController` — export endpoint (audit-logged)
- [x] 4.6 `GenerateDailySummaryJob` + `Schedule::job()->dailyAt('02:00')`

**Phase 4 complete.**

---

## Phase 5 — Integrations

- [x] 5.1 Connected services — migration + model (encrypted tokens)
- [x] 5.2 Fitbit integration — `FitbitService`, OAuth + sync
- [x] 5.3 `IntegrationController` — connect, callback, sync, disconnect
- [x] 5.4 `SyncFitbitDataJob` — queued sync job, scheduled every 4h per connected user
- [x] 5.5 Batch import endpoints — `BatchImportController`, all 5 log types, `client_id` dedup, migration for `client_id` columns
- [x] 5.6 Apple Health XML import — `AppleHealthImportService`, `ProcessAppleHealthImportJob`, `ImportController`

**Phase 5 complete.**

---

## Key Architecture Decisions

| Decision | Choice | Reason |
|----------|--------|--------|
| Auth | Sanctum tokens | API-first, mobile-ready |
| MFA | TOTP via Google2FA | Standard TOTP, recovery codes |
| Encryption | Laravel `Crypt` facade | Field-level AES-256 for health data |
| Data isolation | `UserOwnedScope` global scope | Zero-trust per-user filtering |
| Media | Spatie MediaLibrary | Photo uploads for logs |
| PDF | DomPDF | Medical report export |
| DB (dev) | SQLite | Simple local dev setup |
| Queue (dev) | sync | No queue daemon needed locally |

---

## File Inventory (current)

### Phase 1 — Foundation
| Path | Description |
|------|-------------|
| `app/Models/User.php` | User model with TOTP + Sanctum |
| `app/Models/AuditLog.php` | Polymorphic audit log model |
| `app/Models/Scopes/UserOwnedScope.php` | Global user data isolation scope |
| `app/Models/Traits/BelongsToUser.php` | Trait applied to all user-owned models |
| `app/Services/AuditService.php` | Audit logging service |
| `app/Http/Controllers/Api/V1/AuthController.php` | Auth + MFA endpoints |
| `app/Http/Controllers/Api/V1/UserController.php` | Profile + GDPR endpoints |
| `app/Http/Middleware/ForceHttps.php` | HTTPS redirect (production) |
| `app/Http/Middleware/SessionTimeout.php` | 15-min session timeout |
| `app/Http/Middleware/EnsureTotpVerified.php` | TOTP gate middleware |
| `config/biotracker.php` | App-specific config (points, streaks, security) |
| `app/Enums/` | 8 PHP 8.1 backed enums (ExcretionType, VitalType, etc.) |

### Phase 2 — Core Logging
| Path | Description |
|------|-------------|
| `app/Models/ActivityLog.php` | Activity log with media + encrypted notes |
| `app/Models/ExcretionLog.php` | Excretion log with enums + photos |
| `app/Models/Medication.php` | Medication with encrypted fields |
| `app/Models/MedicationLog.php` | Medication dose log |
| `app/Models/SymptomLog.php` | Symptom log with encrypted fields |
| `app/Models/VitalLog.php` | Vital signs log |
| `app/Http/Controllers/Api/V1/ActivityLogController.php` | CRUD + photo upload |
| `app/Http/Controllers/Api/V1/ExcretionLogController.php` | CRUD + photo upload |
| `app/Http/Controllers/Api/V1/MedicationController.php` | Medication CRUD |
| `app/Http/Controllers/Api/V1/MedicationLogController.php` | Dose log CRUD |
| `app/Http/Controllers/Api/V1/SymptomLogController.php` | Symptom CRUD + photos |
| `app/Http/Controllers/Api/V1/VitalLogController.php` | Vital CRUD + filters |

### Phase 3 — Gamification
| Path | Description |
|------|-------------|
| `app/Services/Scoring/ScoringService.php` | Award points, photo bonus, streak bonus |
| `app/Services/Streak/StreakService.php` | Record activity, check milestones |
| `app/Services/Scoring/AchievementService.php` | Check + unlock achievements |
| `app/Observers/` | 5 log observers wiring scoring/streak/achievement |
| `app/Http/Controllers/Api/V1/PointController.php` | Points balance + history |
| `app/Http/Controllers/Api/V1/StreakController.php` | Current streak |
| `app/Http/Controllers/Api/V1/AchievementController.php` | All achievements + unlock status |
| `app/Http/Controllers/Api/V1/UserTaskController.php` | Task CRUD + complete |

### Phase 4 — Analytics & Reports
| Path | Description |
|------|-------------|
| `app/Services/Analytics/AnalyticsService.php` | Dashboard, trends, daily summary regeneration |
| `app/Services/Reports/ReportExportService.php` | PDF + CSV report generation |
| `app/Http/Controllers/Api/V1/AnalyticsController.php` | Dashboard + trends endpoints |
| `app/Http/Controllers/Api/V1/ReportController.php` | Export endpoint (audit-logged) |
| `app/Jobs/GenerateDailySummaryJob.php` | Nightly batch summary job |
| `resources/views/reports/medical-report.blade.php` | DomPDF Blade template |

### Phase 5 — Integrations
| Path | Description |
|------|-------------|
| `app/Models/ConnectedService.php` | OAuth token store (encrypted) |
| `app/Services/Integrations/FitbitService.php` | Fitbit OAuth2 + data sync |
| `app/Services/Integrations/AppleHealthImportService.php` | Apple Health XML parser |
| `app/Http/Controllers/Api/V1/IntegrationController.php` | Connect / callback / sync / disconnect |
| `app/Http/Controllers/Api/V1/BatchImportController.php` | Bulk import for all 5 log types |
| `app/Http/Controllers/Api/V1/ImportController.php` | Apple Health file upload endpoint |
| `app/Jobs/SyncFitbitDataJob.php` | Queued Fitbit sync per user |
| `app/Jobs/ProcessAppleHealthImportJob.php` | Queued Apple Health XML processing |
| `routes/api.php` | All 70+ API routes |
| `routes/console.php` | Scheduled jobs (nightly summary + 4h Fitbit sync) |

---

## How to Run Locally

```bash
# Ensure PHP 8.2+ is available (Laravel Herd)
# Windows: $env:PATH = "C:\Users\r\.config\herd\bin;$env:PATH"

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

## Post-Build Verification (run after Phase 5)

```bash
php artisan migrate:fresh --seed
php artisan route:list --path=api
```

Then test the 10-point checklist in the original prompt.
