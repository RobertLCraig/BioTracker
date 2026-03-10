# BioTracker — Project Status

Last updated: 2026-03-10T18:00:00Z
Current phase: Phase 3 — Gamification (COMPLETE — see Phase 4 next)
Current step: 4.1

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

- [ ] 4.1 Daily summaries — migration + model
- [ ] 4.2 `AnalyticsService` — dashboard, trends, regenerate summary
- [ ] 4.3 `AnalyticsController` — dashboard + trends endpoints
- [ ] 4.4 `ReportExportService` — PDF + CSV generation via DomPDF
- [ ] 4.5 `ReportController` — export endpoint
- [ ] 4.6 `GenerateDailySummaryJob` + scheduled task

---

## Phase 5 — Integrations

- [ ] 5.1 Connected services — migration + model (encrypted tokens)
- [ ] 5.2 Fitbit integration — `FitbitService`, OAuth + sync
- [ ] 5.3 `IntegrationController` — connect, callback, sync, disconnect
- [ ] 5.4 `SyncFitbitDataJob` — queued sync job
- [ ] 5.5 Batch import endpoints — mobile bulk-push for all log types
- [ ] 5.6 Apple Health XML import — `AppleHealthImportService`

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
| `database/migrations/0001_01_01_000000_create_users_table.php` | Users + TOTP columns |
| `database/migrations/0001_01_01_000003_create_audit_logs_table.php` | Audit log table |
| `database/migrations/2026_03_10_115233_create_media_table.php` | Spatie media table |

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
