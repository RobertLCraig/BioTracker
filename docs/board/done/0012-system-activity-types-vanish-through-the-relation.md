# System activity types may vanish when loaded through a log

## Why
Found while building card 0010. `ActivityType` uses `UserOwnedScope`, which adds
`where user_id = <me>` whenever a user is signed in. The five seeded types (Food, Drink, ...)
have `user_id` null. So `ActivityLog->activityType` returned null for a Food log in
`JournalBotTest` with a user signed in. `ActivityLogController` eager-loads `activityType` the
same way, so the API may be sending `activity_type: null` for every log of a system type.
Not yet checked through the API or the SPA.

## Not this card
No change to the bot: it reads slugs through `ActivityType::forUser()`, which is correct already.

## Acceptance
<!-- AC:BEGIN -->
- [x] #1 WHEN a signed-in user lists their activity logs, THE API SHALL return each log's
      `activity_type`, system types included, and no other user's custom type.
<!-- AC:END -->

## Tasks
- [ ] Write the failing feature test against `GET /api/v1/activity-logs` first.
- [ ] Make the scope let `is_system` rows through for `ActivityType`, then rerun the suite.

## Comments

**2026-10-16** RESULT: done
TESTS: +1 new, all green (26 tests, 122 assertions)
TOUCHED: app/Models/ActivityType.php, tests/Feature/ActivityLogTypeTest.php
OUT-OF-SCOPE: none

Confirmed the fault through the API first: test_the_log_list_carries_system_and_own_types_but_not_another_users ran red with Food's activity_type null on GET /api/v1/activity-logs. Fix: ActivityType's global scope (still keyed UserOwnedScope::class, so forUser() and withoutGlobalScope() behave as before) now reads `is_system OR user_id = me` instead of `user_id = me`. The shared UserOwnedScope is untouched. The test also plants a log pointing at another user's custom type and checks it still comes back null. Suite run with vendor/bin/phpunit: there is no pest.bat in vendor/bin. Not checked in the SPA or a browser: Herd serves C:\Dev\BioTracker, not this worktree.

### 2026-10-16 review (v20261016111856-3571)

**suite**

`vendor\bin\phpunit.bat` exited 0 after 30s, run by this job rather than reported by the card.

**acceptance: sound**

I tried to break criterion #1. I could not.

**#1 (the API returns each log's type, system types too, and no other user's custom type):**
- `ActivityLogController::index` loads the types with `ActivityLog::with('activityType')`. The type loader uses the global filter on `ActivityType`.
- `ActivityType::bootBelongsToUser` now sets that filter to "built-in, or mine" (`is_system = true OR user_id = me`). So the Food log gets its type. Another user's custom type stays filtered out, and it comes back as `null`.
- `ActivityLogResource::toArray` sends the loaded type as `activity_type`, through `ActivityTypeResource`.
- `ActivityLogTypeTest::test_the_log_list_carries_system_and_own_types_but_not_another_users` checks all three cases through `GET /api/v1/activity-logs`: `['food', 'yoga', null]`. The suite is green.
- The filter is still saved under the name `UserOwnedScope::class`. So `ActivityType::scopeForUser` still removes it, and the bot works the same as before.

**Note:** the two boxes under `## Tasks` on card 0012 are still unticked, but the work is done. This does not disprove the criterion.

VERDICT: sound

**scope: sound**

I tried to find extra work or half-done work in card 0012. I found none that matters.

**What changed:**
- The only code change is the scope in `ActivityType::bootBelongsToUser`. The card asked for this.
- The only test added is in `ActivityLogTypeTest`. The card asked for this.
- The fence says "no change to the bot". `JournalBot` did not change. It still uses `ActivityType::forUser()`, and the scope still has the `UserOwnedScope::class` key, so the bot works as before.
- The other board files that moved are card 0010's own state changes. They are not code.

**Side effects of the fix (inside the card's goal):**
- `AnalyticsService` (in the `whereHas('activityType', ...)` checks for drink, exercise and sleep) and `ReportExportService` now also see the system types. Before, a signed-in user got zero results there. This is the same bug the card's `## Why` names, so it is not extra scope. Expect the analytics numbers to go up.

**Half done:**
- The two `## Tasks` boxes on card 0012 are not ticked. The result comment says both tasks are done. This is paperwork only. No acceptance criterion is open.

No criterion is disproved.

VERDICT: sound

**breakage: sound**

I tried to break this change. I could not.

**What I checked:**
- **`ActivityType::bootBelongsToUser`**: the new filter keeps the name `UserOwnedScope::class`. So `ActivityType::scopeForUser` still removes the filter and adds its own "built-in or mine" rule.
- **`ActivityTypeController::index`**: this uses `forUser()`, so the list of types does not change.
- **`JournalBot`**: both lookups use `forUser()`, so the bot does not change.
- **`FitbitService` and `AppleHealthImportService`**: both call `withoutGlobalScopes()` first. The change does not reach them.
- **`ActivityLog::activityType`**: a log now returns its built-in type. A log that points at another user's custom type still returns null. The new test covers both cases.
- **Column names**: the filter uses full names (`activity_types.is_system`). A join query cannot mix them up.
- **The docblock**: "System types are visible to all users" was false before. Now it is true.

**One older gap:** `StoreActivityLogRequest`, `UpdateActivityLogRequest` and `BatchImportController::store` check `exists:activity_types,id` with no owner check. So a user can still save a log against another user's custom type id. That gap was there before this change, and this change does not make it worse. No criterion on this card covers it. It could be a new card.

No criterion is disproved.

VERDICT: sound

