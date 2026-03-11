<?php

namespace App\Services\Analytics;

use App\Models\ActivityLog;
use App\Models\DailySummary;
use App\Models\ExcretionLog;
use App\Models\MedicationLog;
use App\Models\SymptomLog;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\UserStreak;
use App\Models\VitalLog;
use App\Services\Scoring\ScoringService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Provides dashboard data, trend aggregation, and daily summary regeneration.
 */
class AnalyticsService
{
    public function __construct(private ScoringService $scoring) {}

    /**
     * Return a snapshot for the user's dashboard.
     */
    public function dashboard(User $user): array
    {
        $todaySummary = DailySummary::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('date', Carbon::today())
            ->first();

        $streak = UserStreak::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first();

        // Merge recent entries from all log types into a single list
        $recentActivity = collect();

        ActivityLog::withoutGlobalScopes()
            ->with('activityType')
            ->where('user_id', $user->id)
            ->orderByDesc('logged_at')
            ->limit(10)
            ->get()
            ->each(fn ($l) => $recentActivity->push([
                'kind'       => 'activity',
                'id'         => $l->id,
                'label'      => $l->activityType?->name ?? 'Activity',
                'sub'        => collect([$l->duration_minutes ? $l->duration_minutes . ' min' : null, $l->calories ? $l->calories . ' kcal' : null])->filter()->join(' · '),
                'icon'       => $l->activityType?->icon ?? '⭐',
                'logged_at'  => $l->logged_at?->toIso8601String(),
            ]));

        VitalLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderByDesc('logged_at')
            ->limit(5)
            ->get()
            ->each(fn ($l) => $recentActivity->push([
                'kind'      => 'vital',
                'id'        => $l->id,
                'label'     => ucwords(str_replace('_', ' ', $l->type instanceof \BackedEnum ? $l->type->value : $l->type)),
                'sub'       => $l->secondary_value ? "{$l->value} / {$l->secondary_value} {$l->unit}" : "{$l->value} {$l->unit}",
                'icon'      => '🩺',
                'logged_at' => $l->logged_at?->toIso8601String(),
            ]));

        SymptomLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderByDesc('logged_at')
            ->limit(5)
            ->get()
            ->each(fn ($l) => $recentActivity->push([
                'kind'      => 'symptom',
                'id'        => $l->id,
                'label'     => $l->symptom ?? 'Symptom',
                'sub'       => "Severity {$l->severity}/10" . ($l->body_area ? " · {$l->body_area}" : ''),
                'icon'      => '🩹',
                'logged_at' => $l->logged_at?->toIso8601String(),
            ]));

        MedicationLog::withoutGlobalScopes()
            ->with('medication')
            ->where('user_id', $user->id)
            ->orderByDesc('taken_at')
            ->limit(5)
            ->get()
            ->each(fn ($l) => $recentActivity->push([
                'kind'      => 'medication',
                'id'        => $l->id,
                'label'     => $l->medication?->name ?? 'Medication',
                'sub'       => $l->dosage_taken ?? '',
                'icon'      => '💊',
                'logged_at' => $l->taken_at?->toIso8601String(),
            ]));

        $recentLogs = $recentActivity
            ->sortByDesc('logged_at')
            ->values()
            ->take(10);

        $totalPoints = $this->scoring->getBalance($user);

        $latestAchievement = DB::table('user_achievements')
            ->where('user_id', $user->id)
            ->orderByDesc('unlocked_at')
            ->first();

        return [
            'today_summary'      => $todaySummary,
            'current_streak'     => $streak?->current_streak ?? 0,
            'longest_streak'     => $streak?->longest_streak ?? 0,
            'streak_is_active'   => $streak?->isActive() ?? false,
            'recent_logs'        => $recentLogs,
            'total_points'       => $totalPoints,
            'latest_achievement' => $latestAchievement,
        ];
    }

    /**
     * Aggregate daily summaries over a period to produce chart-ready data.
     *
     * @param  string  $period  '7d' | '30d' | '90d'
     * @param  string|null  $type  Optional: 'calories' | 'water' | 'exercise' | 'sleep' | 'points'
     */
    public function trends(User $user, string $period, ?string $type = null): array
    {
        $days = match ($period) {
            '7d'  => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $from = Carbon::today()->subDays($days - 1);

        $summaries = DailySummary::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('date', '>=', $from)
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($s) => $s->date->toDateString());

        // Build a complete date range (fill gaps with zeros)
        $labels   = [];
        $datasets = [
            'calories'  => [],
            'water_ml'  => [],
            'exercise'  => [],
            'sleep'     => [],
            'logs'      => [],
            'points'    => [],
        ];

        for ($i = 0; $i < $days; $i++) {
            $date       = $from->copy()->addDays($i)->toDateString();
            $labels[]   = $date;
            $summary    = $summaries[$date] ?? null;

            $datasets['calories'][]  = $summary?->total_calories ?? 0;
            $datasets['water_ml'][]  = $summary?->total_water_ml ?? 0;
            $datasets['exercise'][]  = $summary?->exercise_minutes ?? 0;
            $datasets['sleep'][]     = (float) ($summary?->sleep_hours ?? 0);
            $datasets['logs'][]      = $summary?->log_count ?? 0;
            $datasets['points'][]    = $summary?->points_earned ?? 0;
        }

        return [
            'period'   => $period,
            'labels'   => $labels,
            'datasets' => $type ? [$type => $datasets[$type] ?? []] : $datasets,
        ];
    }

    /**
     * Recount all source data for a given date and upsert the DailySummary row.
     */
    public function regenerateDailySummary(User $user, Carbon $date): DailySummary
    {
        $dateStr = $date->toDateString();

        // Calories from activity logs
        $calories = ActivityLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereDate('logged_at', $dateStr)
            ->sum('calories');

        // Water (activity logs for 'drink' type)
        $waterMl = ActivityLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereDate('logged_at', $dateStr)
            ->whereHas('activityType', fn ($q) => $q->where('slug', 'drink'))
            ->sum(DB::raw('quantity * CASE WHEN unit = "ml" THEN 1 WHEN unit = "l" THEN 1000 ELSE 250 END'));

        // Exercise minutes
        $exerciseMinutes = ActivityLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereDate('logged_at', $dateStr)
            ->whereHas('activityType', fn ($q) => $q->where('slug', 'exercise'))
            ->sum('duration_minutes');

        // Sleep hours
        $sleepHours = ActivityLog::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereDate('logged_at', $dateStr)
            ->whereHas('activityType', fn ($q) => $q->where('slug', 'sleep'))
            ->sum(DB::raw('duration_minutes / 60.0'));

        // Total log count across all log types
        $logCount = ActivityLog::withoutGlobalScopes()->where('user_id', $user->id)->whereDate('logged_at', $dateStr)->count()
            + ExcretionLog::withoutGlobalScopes()->where('user_id', $user->id)->whereDate('logged_at', $dateStr)->count()
            + MedicationLog::withoutGlobalScopes()->where('user_id', $user->id)->whereDate('taken_at', $dateStr)->count()
            + SymptomLog::withoutGlobalScopes()->where('user_id', $user->id)->whereDate('logged_at', $dateStr)->count()
            + VitalLog::withoutGlobalScopes()->where('user_id', $user->id)->whereDate('logged_at', $dateStr)->count();

        // Points earned on this date
        $pointsEarned = UserPoint::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereDate('created_at', $dateStr)
            ->sum('points');

        return DailySummary::withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $user->id, 'date' => $dateStr],
            [
                'total_calories'   => (int) $calories,
                'total_water_ml'   => (int) $waterMl,
                'exercise_minutes' => (int) $exerciseMinutes,
                'sleep_hours'      => round((float) $sleepHours, 2),
                'log_count'        => $logCount,
                'points_earned'    => (int) $pointsEarned,
            ]
        );
    }
}
