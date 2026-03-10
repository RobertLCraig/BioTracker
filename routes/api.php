<?php

use App\Http\Controllers\Api\V1\AchievementController;
use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\ActivityTypeController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BatchImportController;
use App\Http\Controllers\Api\V1\ExcretionLogController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\MedicationController;
use App\Http\Controllers\Api\V1\MedicationLogController;
use App\Http\Controllers\Api\V1\PointController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\StreakController;
use App\Http\Controllers\Api\V1\SymptomLogController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserTaskController;
use App\Http\Controllers\Api\V1\VitalLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Public ──────────────────────────────────────────────────────────────
    Route::post('/register',    [AuthController::class, 'register']);
    Route::post('/login',       [AuthController::class, 'login']);
    Route::post('/login/totp',  [AuthController::class, 'verifyTotp']);

    // ── Protected ───────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('/logout',              [AuthController::class, 'logout']);
        Route::get('/user',                 [AuthController::class, 'user']);
        Route::post('/user/totp/setup',     [AuthController::class, 'setupTotp']);
        Route::post('/user/totp/confirm',   [AuthController::class, 'confirmTotp']);
        Route::post('/user/totp/disable',   [AuthController::class, 'disableTotp']);

        // User / GDPR
        Route::get('/user/profile',         [UserController::class, 'profile']);
        Route::put('/user/profile',         [UserController::class, 'updateProfile']);
        Route::get('/user/data-export',     [UserController::class, 'exportData']);
        Route::delete('/user/account',      [UserController::class, 'deleteAccount']);

        // Activity
        Route::apiResource('activity-types', ActivityTypeController::class)->only(['index', 'store']);
        Route::apiResource('activity-logs',  ActivityLogController::class);

        // Excretion
        Route::apiResource('excretion-logs', ExcretionLogController::class);

        // Medical
        Route::apiResource('medications',     MedicationController::class);
        Route::apiResource('medication-logs', MedicationLogController::class);
        Route::apiResource('symptom-logs',    SymptomLogController::class);
        Route::apiResource('vital-logs',      VitalLogController::class);

        // ── Phase 3: Gamification ─────────────────────────────────────────────
        Route::get('/points',                 [PointController::class, 'index']);
        Route::get('/points/history',         [PointController::class, 'history']);
        Route::get('/streaks',                [StreakController::class, 'show']);
        Route::get('/achievements',           [AchievementController::class, 'index']);
        Route::apiResource('tasks',           UserTaskController::class);
        Route::post('/tasks/{task}/complete', [UserTaskController::class, 'complete']);

        // ── Phase 4: Analytics ────────────────────────────────────────────────
        Route::get('/analytics/dashboard',   [AnalyticsController::class, 'dashboard']);
        Route::get('/analytics/trends',      [AnalyticsController::class, 'trends']);
        Route::post('/reports/export',       [ReportController::class, 'export']);

        // ── Phase 5: Integrations ─────────────────────────────────────────────
        Route::get('/integrations',                       [IntegrationController::class, 'index']);
        Route::get('/integrations/{provider}/connect',    [IntegrationController::class, 'redirect']);
        Route::get('/integrations/{provider}/callback',   [IntegrationController::class, 'callback']);
        Route::post('/integrations/{provider}/sync',      [IntegrationController::class, 'sync']);
        Route::delete('/integrations/{provider}',         [IntegrationController::class, 'disconnect']);

        // ── Phase 5: Batch imports (mobile bulk-push) ─────────────────────────
        Route::post('/activity-logs/batch',   [BatchImportController::class, 'activityLogs']);
        Route::post('/excretion-logs/batch',  [BatchImportController::class, 'excretionLogs']);
        Route::post('/medication-logs/batch', [BatchImportController::class, 'medicationLogs']);
        Route::post('/symptom-logs/batch',    [BatchImportController::class, 'symptomLogs']);
        Route::post('/vital-logs/batch',      [BatchImportController::class, 'vitalLogs']);

        // ── Phase 5: File imports ─────────────────────────────────────────────
        Route::post('/imports/apple-health',  [ImportController::class, 'appleHealth']);
    });
});
