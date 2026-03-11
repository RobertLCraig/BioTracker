# BioTracker

A personal health journal web application built with Laravel 12 and Vue 3. API-first architecture secured with Sanctum token authentication and optional TOTP MFA. Designed for GDPR/HIPAA compliance with field-level AES-256 encryption on all sensitive health data.

---

## Demo Account

After running `php artisan migrate --seed`, a test account is available:

| Field    | Value              |
|----------|--------------------|
| Email    | `test@example.com` |
| Password | `password`         |
| MFA      | Not enabled        |

> **Note:** This account is for local development only. Do not seed it in production.

---

## Tech Stack

| Layer        | Technology |
|--------------|------------|
| Backend      | PHP 8.2, Laravel 12 |
| Auth         | Laravel Sanctum (token), TOTP MFA via Google2FA |
| Database     | SQLite (dev) — swap to MySQL/Postgres for production |
| Queue        | Sync (dev) — Redis/database for production |
| Frontend     | Vue 3, Vue Router, Pinia, Tailwind CSS 4, Vite |
| Charts       | Chart.js via vue-chartjs |
| Media        | Spatie MediaLibrary (photo uploads) |
| PDF export   | DomPDF (barryvdh/laravel-dompdf) |
| Encryption   | Laravel `Crypt` facade (AES-256 field-level) |

---

## Features

### Health Logging
- **Food** — dedicated food log with meal-time suggestions (breakfast/lunch/dinner/snack based on time of day), food name, quantity, unit, calories, notes, photo attachments; full flexibility to log any food at any time
- **Activity** — exercise, sleep, drink, custom types; calorie and duration tracking; photo attachments
- **Excretion** — type, Bristol scale, colour, blood presence, urgency, pain level; photo attachments
- **Medications** — manage prescriptions with reminder times; "Due around now" panel with one-tap "Take now" logging; browser push notification reminders (Notifications API); log each dose taken
- **Symptoms** — symptom name, severity (1–10), body area, duration; photo attachments
- **Vital signs** — weight, blood pressure, temperature, heart rate, blood sugar, SpO₂

### Gamification
- **Points** — earned per log entry, photo upload, and streak milestone
- **Streaks** — consecutive daily logging tracked automatically
- **Achievements** — 10 unlockable achievements across bronze/silver/gold tiers

### Analytics & Reporting
- **Dashboard** — today's summary (calories, water, exercise, sleep, points, streak)
- **Trends** — 7-day, 30-day, 90-day line charts for all metrics
- **Reports** — PDF or CSV export for any date range and data type selection

### Integrations
- **Fitbit** — OAuth2 connection; syncs heart rate, weight, sleep, steps every 4 hours
- **Apple Health** — XML export import (sync for small files, queued for large)
- **Batch import** — bulk push endpoint for all log types with `client_id` deduplication (mobile-ready)

### Security & Compliance
- HTTPS enforcement middleware (production)
- 15-minute session timeout
- TOTP two-factor authentication (setup, confirm, disable via API)
- Field-level encryption on: `notes`, `symptom`, `medication name`, `access_token`, `refresh_token`
- Per-user data isolation via global `UserOwnedScope` (zero cross-user data leakage)
- Polymorphic audit log on all sensitive actions
- GDPR: data export (`GET /api/v1/user/data-export`), account deletion (`DELETE /api/v1/user/account`)

---

## Quick Start

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment setup
cp .env.example .env
php artisan key:generate

# 3. Database
touch database/database.sqlite
php artisan migrate --seed

# 4. Build frontend assets
npm run build

# 5. Start development server (runs Laravel + Vite + queue + logs concurrently)
composer run dev
```

Then open [http://localhost:8000](http://localhost:8000) and sign in with the demo account above.

---

## API Reference

All endpoints are prefixed with `/api/v1/`. Protected routes require `Authorization: Bearer <token>`.

### Authentication
| Method | Endpoint              | Description                    |
|--------|-----------------------|--------------------------------|
| POST   | `/register`           | Register new account           |
| POST   | `/login`              | Login (returns token or TOTP prompt) |
| POST   | `/login/totp`         | Complete TOTP verification     |
| POST   | `/logout`             | Revoke current token           |
| GET    | `/user`               | Current authenticated user     |
| POST   | `/user/totp/setup`    | Generate TOTP QR code          |
| POST   | `/user/totp/confirm`  | Enable TOTP with verification  |
| POST   | `/user/totp/disable`  | Disable TOTP                   |

### User / GDPR
| Method | Endpoint              | Description                    |
|--------|-----------------------|--------------------------------|
| GET    | `/user/profile`       | Get profile                    |
| PUT    | `/user/profile`       | Update profile                 |
| GET    | `/user/data-export`   | Full JSON data export          |
| DELETE | `/user/account`       | Permanently delete account     |

### Logging
| Method | Endpoint                   | Description              |
|--------|----------------------------|--------------------------|
| GET/POST | `/activity-types`        | List / create custom types |
| GET/POST/PUT/DELETE | `/activity-logs` | Activity CRUD       |
| POST   | `/activity-logs/batch`     | Bulk import              |
| GET/POST/PUT/DELETE | `/excretion-logs` | Excretion CRUD     |
| POST   | `/excretion-logs/batch`    | Bulk import              |
| GET/POST/PUT/DELETE | `/medications`   | Medication CRUD    |
| GET/POST/PUT/DELETE | `/medication-logs` | Dose log CRUD    |
| POST   | `/medication-logs/batch`   | Bulk import              |
| GET/POST/PUT/DELETE | `/symptom-logs`  | Symptom CRUD       |
| POST   | `/symptom-logs/batch`      | Bulk import              |
| GET/POST/PUT/DELETE | `/vital-logs`    | Vital signs CRUD   |
| POST   | `/vital-logs/batch`        | Bulk import              |

### Gamification
| Method | Endpoint                    | Description              |
|--------|-----------------------------|--------------------------|
| GET    | `/points`                   | Balance + recent history |
| GET    | `/points/history`           | Paginated points ledger  |
| GET    | `/streaks`                  | Current streak data      |
| GET    | `/achievements`             | All achievements + unlock status |
| GET/POST/PUT/DELETE | `/tasks`       | User task CRUD     |
| POST   | `/tasks/{task}/complete`    | Complete a task          |

### Analytics & Reports
| Method | Endpoint                    | Description              |
|--------|-----------------------------|--------------------------|
| GET    | `/analytics/dashboard`      | Today's summary + recent activity |
| GET    | `/analytics/trends`         | Chart data (`?period=7d\|30d\|90d`) |
| POST   | `/reports/export`           | Download PDF or CSV report |

### Integrations
| Method | Endpoint                              | Description              |
|--------|---------------------------------------|--------------------------|
| GET    | `/integrations`                       | List connected services  |
| GET    | `/integrations/{provider}/connect`    | OAuth redirect URL       |
| GET    | `/integrations/{provider}/callback`   | OAuth callback + token store |
| POST   | `/integrations/{provider}/sync`       | Queue a manual sync      |
| DELETE | `/integrations/{provider}`            | Disconnect provider      |
| POST   | `/imports/apple-health`               | Upload Apple Health XML  |

Supported providers: `fitbit`

---

## Environment Variables

Add these to `.env` for third-party integrations:

```env
# Fitbit OAuth2
FITBIT_CLIENT_ID=
FITBIT_CLIENT_SECRET=
FITBIT_REDIRECT_URI=http://localhost:8000/api/v1/integrations/fitbit/callback
```

---

## Project Structure

```
app/
├── Enums/                      # PHP 8.1 backed enums (VitalType, ExcretionType, etc.)
├── Http/
│   ├── Controllers/Api/V1/     # 16 API controllers
│   ├── Middleware/             # ForceHttps, SessionTimeout, EnsureTotpVerified
│   └── Resources/              # 13 API resource transformers
├── Jobs/                       # GenerateDailySummaryJob, SyncFitbitDataJob, ProcessAppleHealthImportJob
├── Models/                     # All Eloquent models with BelongsToUser trait
├── Observers/                  # 5 log observers (scoring + streak + achievements on create)
└── Services/
    ├── Analytics/              # AnalyticsService
    ├── Integrations/           # FitbitService, AppleHealthImportService
    ├── Reports/                # ReportExportService
    └── Scoring/                # ScoringService, AchievementService, StreakService

resources/js/
├── components/                 # AppLayout, LogList, PhotoDropzone
├── composables/                # useApi (Axios + multipart wrapper)
├── router/                     # Vue Router with auth guards
├── stores/                     # Pinia auth store
└── views/                      # 11 page views (incl. Food) + auth views
```

---

## Scheduled Jobs

| Schedule       | Job                          | Description                        |
|----------------|------------------------------|------------------------------------|
| Daily at 02:00 | `GenerateDailySummaryJob`    | Regenerates daily summaries for all users |
| Every 4 hours  | Fitbit sync closure          | Dispatches `SyncFitbitDataJob` for each connected user |

Run the scheduler locally with:
```bash
php artisan schedule:work
```

---

## Seeded Data

Running `php artisan migrate --seed` creates:

- **Activity types** (system): Food, Drink, Exercise, Sleep, Custom
- **Achievements** (10 total): First Log, Getting Started, Week Warrior, Fortnight Fighter, Month Master, Century Club, Photographer, Health Historian, Pill Tracker, Gut Instinct
- **Test user**: `test@example.com` / `password`

### Demo Data (DemoDataSeeder)

Automatically runs as part of `php artisan migrate --seed` (or standalone: `php artisan db:seed --class=DemoDataSeeder`).

The demo account is pre-populated with **182 days (26 weeks) of realistic health data** showing a health-improvement journey:

| Metric | Start (week 1) | End (week 26) |
|--------|----------------|---------------|
| Weight | ~87.2 kg | ~81.4 kg |
| Blood pressure | ~136/88 mmHg | ~122/79 mmHg |
| Resting heart rate | ~80 bpm | ~62 bpm |
| Daily calories | ~2,400 kcal | ~2,000 kcal |
| Sleep | ~6.2 h/night | ~7.8 h/night |
| Exercise frequency | 3×/week | 5×/week |

Includes: food logs (breakfast/lunch/dinner/snacks), water intake, exercise sessions (running, cycling, weights, swimming, HIIT), weekly weight + twice-weekly blood pressure + post-exercise heart rate, daily excretion logs, symptom logs, medication logs (Lisinopril + Vitamin D3), all achievements unlocked, 182-day streak, and accumulated gamification points.

The seeder is **idempotent** — re-running when demo data already exists is a no-op.

---

## Roadmap

### Food Database & AI Calorie Estimation

A future version will add a structured food database and intelligent calorie estimation:

#### Phase 1 — Food Item Database
- `food_items` table: name, brand, serving size, calories per 100 g, macros (protein/fat/carbs/fibre), barcode
- Seed with Open Food Facts data (~2.5 M items, MIT licensed)
- `GET /api/v1/food/search?q=` — instant full-text search
- Link food log entries to a canonical `food_item_id` (nullable — manual entry still supported)

#### Phase 2 — Barcode Scanning
- Mobile-friendly barcode scanner (BarcodeDetector API or ZXing-JS)
- `GET /api/v1/food/barcode/{ean}` — look up item by EAN-13/UPC

#### Phase 3 — Photo-based Portion & Calorie Estimation
- User uploads a photo of their plate + an optional reference object (coin, credit card) for scale
- Server-side pipeline:
  1. **Object detection** (YOLO v8 / Detectron2) — identify food items in the image
  2. **Depth / volume estimation** — estimate portion size from reference object or known dish dimensions
  3. **Calorie lookup** — match detected items to food database entries
  4. **Claude Vision API** — fallback natural-language description → calorie estimate for unrecognised foods
- Returns a pre-filled food log form with suggested food name, quantity, and calories for user review before saving
- All AI suggestions are editable — user always has final say
- Architecture: queued job (`EstimateCaloriesFromPhotoJob`), stores intermediate results in `photo_analysis_results` table, pushes result via Laravel Echo / Pusher when ready

#### Phase 4 — Meal Plans
- Template meals users can save and re-log with one click
- Weekly meal planner with calorie budget tracking
- Nutritional summaries (macros, vitamins) on dashboard
