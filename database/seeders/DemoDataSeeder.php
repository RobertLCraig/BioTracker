<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\ActivityLog;
use App\Models\ActivityType;
use App\Models\ExcretionLog;
use App\Models\Medication;
use App\Models\MedicationLog;
use App\Models\SymptomLog;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\UserStreak;
use App\Models\VitalLog;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds 6 months (~182 days) of realistic demo health data for the test user.
 *
 * Narrative: Alex (test@example.com) started a health improvement journey 26 weeks
 * ago. They were overweight (87 kg), had elevated blood pressure, poor sleep and
 * little exercise. Over the period you can see gradual improvements in all metrics.
 *
 * Run:  php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    // ── Food catalogue  (name → [min_kcal, max_kcal]) ────────────────────────
    private const BREAKFASTS = [
        ['Porridge with banana',            320, 390],
        ['Scrambled eggs on toast',         380, 450],
        ['Greek yoghurt with berries',      250, 310],
        ['Wholegrain toast with avocado',   360, 420],
        ['Overnight oats with chia seeds',  340, 400],
        ['Boiled eggs and whole-rye bread', 300, 370],
        ['Smoothie bowl',                   310, 380],
        ['Muesli with semi-skimmed milk',   360, 420],
    ];

    private const LUNCHES = [
        ['Chicken & quinoa salad',          420, 510],
        ['Tuna wrap with spinach',          390, 480],
        ['Lentil soup with crusty bread',   450, 530],
        ['Grilled salmon with rice',        520, 610],
        ['Chickpea and vegetable stir-fry', 410, 490],
        ['Turkey and hummus sandwich',      430, 510],
        ['Prawn and avocado salad',         360, 440],
        ['Jacket potato with cottage cheese', 480, 560],
        ['Bean burrito bowl',               490, 570],
        ['Sushi platter',                   380, 460],
    ];

    private const DINNERS = [
        ['Grilled chicken breast with sweet potato', 540, 660],
        ['Beef stir-fry with noodles',               580, 700],
        ['Baked salmon with asparagus',              490, 590],
        ['Vegetable curry with brown rice',          520, 640],
        ['Turkey bolognese with courgetti',          480, 580],
        ['Lamb chops with roasted veg',              620, 750],
        ['Prawn pasta in tomato sauce',              550, 670],
        ['Tofu and broccoli stir-fry',               420, 520],
        ['Chicken tikka masala with rice',           650, 780],
        ['Grilled sea bass with green beans',        460, 560],
        ['Pork tenderloin with mashed potato',       590, 720],
        ['Stuffed bell peppers',                     430, 530],
    ];

    private const SNACKS = [
        ['Apple and almond butter',    180, 220],
        ['Mixed nuts (small handful)', 160, 200],
        ['Protein bar',                200, 250],
        ['Carrot and hummus',          120, 160],
        ['Banana',                      90, 110],
        ['Rice cakes with cottage cheese', 140, 180],
        ['Dark chocolate (2 squares)', 100, 140],
    ];

    private const EXERCISES = [
        ['slug' => 'exercise', 'name' => 'Running',         'unit' => 'km',  'qty_min' => 3, 'qty_max' => 8,   'dur_min' => 25, 'dur_max' => 60, 'kcal_min' => 250, 'kcal_max' => 650],
        ['slug' => 'exercise', 'name' => 'Cycling',         'unit' => 'km',  'qty_min' => 10, 'qty_max' => 30, 'dur_min' => 30, 'dur_max' => 90, 'kcal_min' => 300, 'kcal_max' => 750],
        ['slug' => 'exercise', 'name' => 'Weight Training', 'unit' => 'min', 'qty_min' => 45, 'qty_max' => 65, 'dur_min' => 45, 'dur_max' => 65, 'kcal_min' => 250, 'kcal_max' => 450],
        ['slug' => 'exercise', 'name' => 'Swimming',        'unit' => 'min', 'qty_min' => 30, 'qty_max' => 50, 'dur_min' => 30, 'dur_max' => 50, 'kcal_min' => 280, 'kcal_max' => 500],
        ['slug' => 'exercise', 'name' => 'HIIT',            'unit' => 'min', 'qty_min' => 20, 'qty_max' => 30, 'dur_min' => 20, 'dur_max' => 30, 'kcal_min' => 250, 'kcal_max' => 400],
    ];

    private const SYMPTOMS = [
        ['Headache',       'Head',       4, 7,  30, 180],
        ['Fatigue',        null,         3, 6,  60, 360],
        ['Lower back pain','Lower back', 3, 6,  60, 240],
        ['Muscle soreness','Legs',       2, 5,  60, 180],
        ['Mild nausea',    'Abdomen',    2, 4,  30,  90],
        ['Stiff neck',     'Neck',       3, 5,  45, 120],
        ['Bloating',       'Abdomen',    2, 4,  60, 180],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            $this->command->warn('Demo user (test@example.com) not found. Run DatabaseSeeder first.');
            return;
        }

        // Idempotency: skip if already seeded
        if (ActivityLog::withoutGlobalScopes()->where('user_id', $user->id)->count() > 20) {
            $this->command->info('Demo data already seeded — skipping.');
            return;
        }

        $this->command->info('Seeding 6 months of demo health data…');

        // Load system activity types (bypass user scope)
        $types = ActivityType::withoutGlobalScopes()
            ->where('is_system', true)
            ->get()
            ->keyBy('slug');

        if ($types->isEmpty()) {
            $this->command->warn('Activity types not found. Run ActivityTypeSeeder first.');
            return;
        }

        // Create medications (encrypted via model mutators)
        [$lisinopril, $vitaminD] = $this->seedMedications($user);

        $startDate = Carbon::today()->subDays(182)->startOfDay();
        $totalPoints = 0;

        DB::transaction(function () use ($user, $types, $lisinopril, $vitaminD, $startDate, &$totalPoints) {
            for ($day = 0; $day < 182; $day++) {
                $date     = $startDate->copy()->addDays($day);
                $progress = $day / 181; // 0.0 → 1.0 over the journey

                $dailyPoints = $this->seedDay($user, $types, $lisinopril, $vitaminD, $date, $day, $progress);
                $totalPoints += $dailyPoints;

                // Regenerate daily summary so trend charts have historical data
                app(AnalyticsService::class)->regenerateDailySummary($user, $date);

                if ($day % 30 === 0) {
                    $this->command->info("  Day {$day}/182 seeded…");
                }
            }
        });

        $this->command->info('Seeding streak, points, achievements…');
        $this->seedStreak($user, 182);
        $this->seedPointsSummary($user, $totalPoints);
        $this->unlockAchievements($user);

        $this->command->info('✓ Demo data seeded. ' . number_format($totalPoints) . ' points, 182-day streak.');
    }

    // ── Per-day seeding ───────────────────────────────────────────────────────

    private function seedDay(
        User $user,
        $types,
        Medication $lisinopril,
        Medication $vitaminD,
        Carbon $date,
        int  $day,
        float $progress,
    ): int {
        $points = 0;
        $dow    = (int) $date->dayOfWeek; // 0=Sun … 6=Sat

        // Calorie budget trends downward (2400 kcal → 2000 kcal)
        $calorieTarget = (int) (2400 - 400 * $progress);

        // ── Sleep ────────────────────────────────────────────────────────────
        // Sleep improves from ~6.2 h to ~7.8 h average
        $sleepHours = $this->gaussian(6.2 + 1.6 * $progress, 0.6);
        $sleepHours = max(4.5, min(10.0, $sleepHours));
        $sleepMin   = (int) round($sleepHours * 60);

        $log = new ActivityLog([
            'user_id'          => $user->id,
            'activity_type_id' => $types['sleep']->id,
            'logged_at'        => $date->copy()->setTime(7, 0),
            'duration_minutes' => $sleepMin,
            'notes'            => null,
            'metadata'         => null,
        ]);
        $log->saveQuietly();
        $points += $types['sleep']->points_per_log;

        // ── Food ─────────────────────────────────────────────────────────────
        $points += $this->seedFood($user, $types['food']->id, $date, $calorieTarget, $progress);

        // ── Water ────────────────────────────────────────────────────────────
        $points += $this->seedWater($user, $types['drink']->id, $date);

        // ── Exercise ─────────────────────────────────────────────────────────
        // Frequency increases from 3/wk to 5/wk
        $exerciseDays = $progress < 0.5 ? [1, 3, 5] : [1, 2, 3, 5, 6]; // Mon/Wed/Fri → Mon–Wed/Fri/Sat
        if (in_array($dow, $exerciseDays)) {
            $points += $this->seedExercise($user, $types['exercise']->id, $date, $progress);
        }

        // ── Vitals ────────────────────────────────────────────────────────────
        // Weight every Sunday
        if ($dow === 0) {
            $this->seedWeight($user, $date, $day, $progress);
        }
        // Blood pressure Mon + Thu
        if (in_array($dow, [1, 4])) {
            $this->seedBloodPressure($user, $date, $progress);
        }
        // Heart rate on exercise days
        if (in_array($dow, $exerciseDays)) {
            $this->seedHeartRate($user, $date, $progress);
        }

        // ── Excretion ─────────────────────────────────────────────────────────
        $this->seedExcretion($user, $date);

        // ── Medications ───────────────────────────────────────────────────────
        $this->seedMedicationLog($user, $lisinopril, $date);
        if ($day >= 91) { // Vitamin D started halfway through
            $this->seedMedicationLog($user, $vitaminD, $date);
        }

        // ── Symptoms (occasional) ─────────────────────────────────────────────
        // Frequency drops with progress: ~1/week early, ~1/3wk later
        $symptomChance = (int) (14 + 28 * $progress); // day interval
        if ($day % $symptomChance < 2) {
            $this->seedSymptom($user, $date, $progress);
        }

        return $points;
    }

    // ── Food ─────────────────────────────────────────────────────────────────

    private function seedFood(User $user, int $typeId, Carbon $date, int $calorieTarget, float $progress): int
    {
        // Breakfast
        $b   = $this->pick(self::BREAKFASTS);
        $bKcal = $this->randInt($b[1], $b[2]);
        $this->makeActivityLog($user, $typeId, $date->copy()->setTime($this->randInt(7, 9), $this->randInt(0, 59)), [
            'notes'    => null,
            'calories' => $bKcal,
            'quantity' => 1,
            'unit'     => 'serving',
            'metadata' => ['food_name' => $b[0], 'meal_label' => 'Breakfast'],
        ]);

        // Lunch
        $l   = $this->pick(self::LUNCHES);
        $lKcal = $this->randInt($l[1], $l[2]);
        $this->makeActivityLog($user, $typeId, $date->copy()->setTime($this->randInt(12, 13), $this->randInt(0, 59)), [
            'notes'    => null,
            'calories' => $lKcal,
            'quantity' => 1,
            'unit'     => 'serving',
            'metadata' => ['food_name' => $l[0], 'meal_label' => 'Lunch'],
        ]);

        // Dinner (scale to meet calorie target)
        $remainingKcal = max(400, $calorieTarget - $bKcal - $lKcal);
        $d   = $this->pick(self::DINNERS);
        $dKcal = min($remainingKcal, $this->randInt($d[1], $d[2]));
        $this->makeActivityLog($user, $typeId, $date->copy()->setTime($this->randInt(18, 20), $this->randInt(0, 59)), [
            'notes'    => null,
            'calories' => $dKcal,
            'quantity' => 1,
            'unit'     => 'serving',
            'metadata' => ['food_name' => $d[0], 'meal_label' => 'Dinner'],
        ]);

        // Snack (70 % chance, less likely as progress improves)
        if (mt_rand(0, 99) < (int) (70 - 40 * $progress)) {
            $s   = $this->pick(self::SNACKS);
            $this->makeActivityLog($user, $typeId, $date->copy()->setTime($this->randInt(15, 17), $this->randInt(0, 59)), [
                'notes'    => null,
                'calories' => $this->randInt($s[1], $s[2]),
                'quantity' => 1,
                'unit'     => 'serving',
                'metadata' => ['food_name' => $s[0], 'meal_label' => 'Afternoon snack'],
            ]);
        }

        return 5; // base food points (simplified)
    }

    // ── Water ─────────────────────────────────────────────────────────────────

    private function seedWater(User $user, int $typeId, Carbon $date): int
    {
        $glasses = $this->randInt(4, 8);
        for ($g = 0; $g < $glasses; $g++) {
            $hour = 7 + (int) ($g * (14 / $glasses)) + $this->randInt(0, 1);
            $this->makeActivityLog($user, $typeId, $date->copy()->setTime(min(22, $hour), $this->randInt(0, 59)), [
                'quantity' => 250,
                'unit'     => 'ml',
                'calories' => 0,
                'metadata' => ['food_name' => 'Water', 'meal_label' => 'Drink'],
            ]);
        }
        return 5;
    }

    // ── Exercise ──────────────────────────────────────────────────────────────

    private function seedExercise(User $user, int $typeId, Carbon $date, float $progress): int
    {
        $ex   = $this->pick(self::EXERCISES);
        $qty  = $this->randFloat($ex['qty_min'], $ex['qty_max']);
        // Performance improves with progress
        $kcal = (int) (($ex['kcal_min'] + ($ex['kcal_max'] - $ex['kcal_min']) * $progress) + $this->randInt(-30, 30));
        $dur  = (int) (($ex['dur_min'] + ($ex['dur_max'] - $ex['dur_min']) * mt_rand(0, 10) / 10));

        $this->makeActivityLog($user, $typeId, $date->copy()->setTime($this->randInt(6, 8), $this->randInt(0, 59)), [
            'duration_minutes' => $dur,
            'quantity'         => round($qty, 1),
            'unit'             => $ex['unit'],
            'calories'         => $kcal,
            'notes'            => null,
            'metadata'         => ['exercise_name' => $ex['name']],
        ]);

        return 10;
    }

    // ── Vitals ────────────────────────────────────────────────────────────────

    private function seedWeight(User $user, Carbon $date, int $day, float $progress): void
    {
        // 87.2 → 81.4 kg with ±0.5 kg noise
        $weight = round(87.2 - 5.8 * $progress + $this->gaussian(0, 0.35), 1);
        $log = new VitalLog([
            'user_id'   => $user->id,
            'type'      => 'weight',
            'value'     => $weight,
            'unit'      => 'kg',
            'logged_at' => $date->copy()->setTime(7, $this->randInt(0, 30)),
            'source'    => 'manual',
        ]);
        $log->saveQuietly();
    }

    private function seedBloodPressure(User $user, Carbon $date, float $progress): void
    {
        // Systolic: 136→122, Diastolic: 88→79
        $sys = (int) round(136 - 14 * $progress + $this->gaussian(0, 3));
        $dia = (int) round(88  - 9  * $progress + $this->gaussian(0, 2));
        $log = new VitalLog([
            'user_id'         => $user->id,
            'type'            => 'blood_pressure',
            'value'           => $sys,
            'secondary_value' => $dia,
            'unit'            => 'mmHg',
            'logged_at'       => $date->copy()->setTime(8, $this->randInt(0, 30)),
            'source'          => 'manual',
        ]);
        $log->saveQuietly();
    }

    private function seedHeartRate(User $user, Carbon $date, float $progress): void
    {
        // Resting HR improves: 80→62 bpm
        $hr = (int) round(80 - 18 * $progress + $this->gaussian(0, 3));
        $log = new VitalLog([
            'user_id'   => $user->id,
            'type'      => 'heart_rate',
            'value'     => $hr,
            'unit'      => 'bpm',
            'logged_at' => $date->copy()->setTime($this->randInt(9, 11), $this->randInt(0, 59)),
            'source'    => 'manual',
        ]);
        $log->saveQuietly();
    }

    // ── Excretion ─────────────────────────────────────────────────────────────

    private function seedExcretion(User $user, Carbon $date): void
    {
        // One poop per day (Bristol type 3 or 4, occasionally 2/5)
        $bristolOptions = [3, 3, 3, 4, 4, 4, 4, 2, 5];
        $bristol = $bristolOptions[array_rand($bristolOptions)];
        $log = new ExcretionLog([
            'user_id'     => $user->id,
            'type'        => 'poop',
            'size'        => $this->pick(['small', 'medium', 'medium', 'medium', 'large']),
            'consistency' => $bristol,
            'colour'      => $this->pick(['Brown', 'Brown', 'Brown', 'Dark brown', 'Light brown']),
            'has_blood'   => false,
            'blood_amount'=> 'none',
            'urgency'     => $this->randInt(1, 3),
            'pain_level'  => $this->randInt(0, 2),
            'logged_at'   => $date->copy()->setTime($this->randInt(7, 9), $this->randInt(0, 59)),
        ]);
        $log->saveQuietly();

        // One pee (morning)
        $pee = new ExcretionLog([
            'user_id'  => $user->id,
            'type'     => 'pee',
            'has_blood'=> false,
            'blood_amount' => 'none',
            'logged_at'=> $date->copy()->setTime(7, $this->randInt(0, 20)),
        ]);
        $pee->saveQuietly();
    }

    // ── Medications ───────────────────────────────────────────────────────────

    private function seedMedicationLog(User $user, Medication $med, Carbon $date): void
    {
        $log = new MedicationLog([
            'user_id'       => $user->id,
            'medication_id' => $med->id,
            'taken_at'      => $date->copy()->setTime(8, $this->randInt(0, 20)),
            'dosage_taken'  => $med->dosage,
        ]);
        $log->saveQuietly();
    }

    // ── Symptoms ─────────────────────────────────────────────────────────────

    private function seedSymptom(User $user, Carbon $date, float $progress): void
    {
        $s = self::SYMPTOMS[array_rand(self::SYMPTOMS)];
        // Severity decreases with progress
        $severity = $this->randInt(
            (int) max(1, $s[2] - (int) round(2 * $progress)),
            (int) max(2, $s[3] - (int) round(2 * $progress))
        );
        $dur = $this->randInt($s[4], $s[5]);

        $log = new SymptomLog([
            'user_id'          => $user->id,
            'symptom'          => $s[0],
            'severity'         => $severity,
            'body_area'        => $s[1],
            'duration_minutes' => $dur,
            'logged_at'        => $date->copy()->setTime($this->randInt(9, 20), $this->randInt(0, 59)),
        ]);
        $log->saveQuietly();
    }

    // ── Medications setup ────────────────────────────────────────────────────

    private function seedMedications(User $user): array
    {
        $lisinopril = new Medication([
            'user_id'        => $user->id,
            'name'           => 'Lisinopril',
            'dosage'         => '10mg',
            'unit'           => 'mg',
            'frequency'      => 'Once daily (morning)',
            'prescribed_by'  => 'Dr J. Williams',
            'notes'          => 'Take with water. Monitor BP regularly.',
            'is_active'      => true,
            'reminder_times' => ['08:00'],
        ]);
        $lisinopril->saveQuietly();

        $vitaminD = new Medication([
            'user_id'        => $user->id,
            'name'           => 'Vitamin D3',
            'dosage'         => '1000 IU',
            'unit'           => 'IU',
            'frequency'      => 'Once daily (morning)',
            'prescribed_by'  => null,
            'notes'          => 'Supplement — bone and immune health.',
            'is_active'      => true,
            'reminder_times' => ['08:00'],
        ]);
        $vitaminD->saveQuietly();

        return [$lisinopril, $vitaminD];
    }

    // ── Streak ───────────────────────────────────────────────────────────────

    private function seedStreak(User $user, int $days): void
    {
        UserStreak::withoutGlobalScopes()
            ->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'current_streak'  => $days,
                    'longest_streak'  => $days,
                    'last_logged_date'=> Carbon::today()->toDateString(),
                ]
            );
    }

    // ── Points ───────────────────────────────────────────────────────────────

    private function seedPointsSummary(User $user, int $total): void
    {
        // Insert a single summary point record rather than per-log records
        // (per-log records would normally be created by the ScoringService observer)
        $record = new UserPoint([
            'user_id' => $user->id,
            'points'  => $total,
            'reason'  => 'Demo data seed — 6 months of health tracking',
        ]);
        $record->saveQuietly();

        // Streak milestone bonuses (7, 14, 30, 60, 90, 180 days)
        foreach ([7, 14, 30, 60, 90, 180] as $milestone) {
            $bonus = new UserPoint([
                'user_id' => $user->id,
                'points'  => $milestone >= 30 ? 100 : 50,
                'reason'  => "{$milestone}-day streak bonus",
            ]);
            $bonus->saveQuietly();
        }
    }

    // ── Achievements ─────────────────────────────────────────────────────────

    private function unlockAchievements(User $user): void
    {
        $all = Achievement::all();
        if ($all->isEmpty()) {
            return;
        }

        $already = DB::table('user_achievements')->where('user_id', $user->id)->pluck('achievement_id')->all();

        $rows = [];
        $count = $all->count();
        foreach ($all as $i => $achievement) {
            if (in_array($achievement->id, $already)) {
                continue;
            }
            // Spread unlock dates across the 6-month journey
            $daysAgo = (int) max(0, 182 - ($i + 1) * intdiv(182, max(1, $count)));
            $rows[] = [
                'user_id'        => $user->id,
                'achievement_id' => $achievement->id,
                'unlocked_at'    => Carbon::now()->subDays($daysAgo)->toDateTimeString(),
                'created_at'     => now()->toDateTimeString(),
                'updated_at'     => now()->toDateTimeString(),
            ];
        }

        if (! empty($rows)) {
            DB::table('user_achievements')->insert($rows);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeActivityLog(User $user, int $typeId, Carbon $at, array $fields): void
    {
        $log = new ActivityLog(array_merge([
            'user_id'          => $user->id,
            'activity_type_id' => $typeId,
            'logged_at'        => $at,
        ], $fields));
        $log->saveQuietly();
    }

    /** Uniform random int [min, max] */
    private function randInt(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /** Uniform random float [min, max] */
    private function randFloat(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    /** Box-Muller normal distribution with given mean and std-dev */
    private function gaussian(float $mean, float $stddev): float
    {
        $u1 = (mt_rand(1, mt_getrandmax() - 1)) / mt_getrandmax();
        $u2 = (mt_rand(1, mt_getrandmax() - 1)) / mt_getrandmax();
        return $mean + $stddev * sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }

    /** Pick a random item from an array */
    private function pick(array $arr): mixed
    {
        return $arr[array_rand($arr)];
    }
}
