<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ActivityType;
use App\Models\Medication;
use App\Models\MedicationLog;
use App\Models\SymptomLog;
use App\Models\User;
use App\Models\VitalLog;
use App\Services\Bot\JournalBot;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class JournalBotTest extends TestCase
{
    use RefreshDatabase;

    private User $rob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ActivityTypeSeeder::class);
        $this->rob = User::factory()->create();
        config([
            'services.telegram.token' => 'T',
            'services.telegram.chat_id' => '42',
            'services.telegram.user_id' => $this->rob->id,
            'services.telegram.timezone' => 'Europe/London',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    private function claudeSays(array $answer): void
    {
        $result = json_encode(['type' => 'result', 'is_error' => false, 'structured_output' => $answer]);
        Process::fake(['*' => Process::result('{"type":"system"}'."\n".$result)]);
    }

    private function message(string $text, int $chat = 42): void
    {
        app(JournalBot::class)->handleUpdate(['update_id' => 1, 'message' => ['chat' => ['id' => $chat], 'text' => $text]]);
    }

    private function sentText(): string
    {
        $sent = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/sendMessage'))->last();

        return $sent ? $sent[0]['text'] : '';
    }

    public function test_a_meal_is_logged_for_the_configured_user_in_utc(): void
    {
        $this->claudeSays(['reply' => 'Logged a cheese sandwich, ~500 kcal.', 'entries' => [[
            'kind' => 'food', 'logged_at' => '2026-07-01T13:00:00+01:00', 'description' => 'Cheese sandwich',
            'calories' => 500, 'protein_g' => 20, 'carbs_g' => 50, 'fat_g' => 22,
        ]]]);

        $this->message('cheese sandwich for lunch');

        $log = ActivityLog::withoutGlobalScopes()->sole();
        $this->assertSame($this->rob->id, $log->user_id);
        $this->assertSame('food', ActivityType::withoutGlobalScopes()->find($log->activity_type_id)->slug);
        $this->assertSame('Cheese sandwich', $log->notes);
        $this->assertSame(500, $log->calories);
        $this->assertSame(['source' => 'telegram', 'protein_g' => 20, 'carbs_g' => 50, 'fat_g' => 22], $log->metadata);
        $this->assertSame('2026-07-01 12:00:00', $log->logged_at->format('Y-m-d H:i:s'), 'BST 13:00 is stored as 12:00 UTC');
        $this->assertSame('Logged a cheese sandwich, ~500 kcal.', $this->sentText());
        Process::assertRan(function ($p) {
            $tools = array_search('--tools', $p->command, true);

            return $tools !== false && $p->command[$tools + 1] === '' // every tool off
                && str_contains($p->input, 'cheese sandwich for lunch');
        });
    }

    public function test_the_next_message_carries_the_conversation_and_todays_log(): void
    {
        $this->claudeSays(['reply' => 'Logged toast.', 'entries' => [[
            'kind' => 'food', 'logged_at' => 'now', 'description' => 'Toast', 'calories' => 150,
        ]]]);
        $this->message('toast');

        $this->claudeSays(['reply' => 'Nice.', 'entries' => []]);
        $this->message('how am I doing?');

        Process::assertRan(fn ($p) => str_contains($p->input, 'food: Toast, 150 kcal')
            && str_contains($p->input, 'You: Logged toast.')
            && str_contains($p->input, 'how am I doing?'));
    }

    public function test_a_weigh_in_is_saved_and_shown_next_time(): void
    {
        $this->claudeSays(['reply' => 'Logged 95.2 kg.', 'entries' => [
            ['kind' => 'weight', 'logged_at' => 'now', 'description' => 'Morning weigh-in', 'quantity' => 95.2, 'unit' => 'kg'],
        ]]);
        $this->message('weighed 95.2');

        $w = VitalLog::withoutGlobalScopes()->sole();
        $this->assertSame([$this->rob->id, 'weight', 'kg'], [$w->user_id, $w->type->value, $w->unit]);
        $this->assertEquals(95.2, $w->value);

        $this->claudeSays(['reply' => 'Down 1.2 kg.', 'entries' => []]);
        $this->message('how is my weight going?');
        Process::assertRan(fn ($p) => str_contains($p->input, ': 95.2')); // value in the weigh-ins list
    }

    private function pollenForecast(int $grassTomorrow): void
    {
        $times = [now('Europe/London')->format('Y-m-d').'T12:00', now('Europe/London')->addDay()->format('Y-m-d').'T12:00'];
        Http::fake(['air-quality-api.open-meteo.com/*' => Http::response(['hourly' => [
            'time' => $times, 'grass_pollen' => [200, $grassTomorrow], 'birch_pollen' => [0, 0],
        ]]), 'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    public function test_low_pollen_tomorrow_sends_nothing(): void
    {
        $this->pollenForecast(12); // today is high, tomorrow is not: no message
        $this->artisan('telegram:pollen')->assertSuccessful();
        $this->assertSame('', $this->sentText());
    }

    public function test_high_pollen_tomorrow_sends_a_message(): void
    {
        $this->pollenForecast(85);
        $this->artisan('telegram:pollen')->assertSuccessful();
        $this->assertStringContainsString('Pollen will be high tomorrow (grass 85', $this->sentText());
    }

    public function test_something_unloggable_becomes_a_feature_request(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'req');
        config(['services.telegram.requests_file' => $file]);
        $this->claudeSays(['reply' => 'Noted as a request.', 'entries' => [], 'feature_requests' => [
            ['title' => 'Log water intake by glasses', 'why' => 'A glass count is quicker than ml.'],
        ]]);

        $this->message('3 glasses of water');

        $line = json_decode(trim(file_get_contents($file)), true);
        $this->assertSame(['Log water intake by glasses', 'A glass count is quicker than ml.'], [$line['title'], $line['why']]);
        unlink($file);
    }

    public function test_pulled_requests_become_numbered_cards_in_human_review(): void
    {
        $board = sys_get_temp_dir().'/board-'.uniqid();
        mkdir("$board/human-review", 0777, true);
        mkdir("$board/done");
        touch("$board/done/0013-older-card.md");
        config(['services.telegram.board_path' => $board]);
        Process::fake([
            '*ssh*' => Process::result(json_encode(['title' => 'Log bowel movements', 'why' => 'Not loggable yet.', 'date' => '2026-10-16'])."\nnot json\n"),
            '*' => Process::result(),
        ]);

        $this->artisan('bot:pull-requests')->assertSuccessful();

        $card = file_get_contents("$board/human-review/0014-log-bowel-movements.md");
        $this->assertStringContainsString("# Log bowel movements\n\n## Why\nNot loggable yet.", $card);
        $this->assertCount(1, glob("$board/human-review/*.md"), 'the non-JSON line is skipped');
        Process::assertRan(fn ($p) => $p->command[0] === 'git' && $p->command[1] === 'commit');
        Process::assertRan(fn ($p) => $p->command[0] === 'ssh' && str_contains(end($p->command), 'rm -f'));
        array_map('unlink', glob("$board/*/*.md"));
    }

    public function test_a_dropped_connection_is_retried_and_errors_never_carry_the_token(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            if (++$calls === 1) {
                throw new ConnectionException('Resolving timed out for https://api.telegram.org/botT/sendMessage');
            }

            return Http::response(['ok' => true, 'result' => []]);
        });
        app(JournalBot::class)->send('42', 'hi');
        $this->assertSame(2, $calls, 'the first, failed attempt was retried');

        config(['services.telegram.token' => 'SECRET123']);
        Http::fake(fn () => throw new ConnectionException('timed out for https://api.telegram.org/botSECRET123/getUpdates'));
        try {
            app(JournalBot::class)->telegram('getUpdates');
            $this->fail('expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('SECRET123', $e->getMessage());
            $this->assertStringContainsString('bot[token]/getUpdates', $e->getMessage());
        }
    }

    /** setUp's fake answers first and Http::fake only appends, so these tests start from none. */
    private function fakeHttpOnly(array|\Closure $fake): void
    {
        Http::swap(new HttpFactory);
        Http::fake($fake);
    }

    private const TOAST = ['reply' => 'Logged toast.', 'entries' => [[
        'kind' => 'food', 'logged_at' => '2026-07-01T08:00:00+01:00', 'description' => 'Toast', 'calories' => 200,
    ]]];

    /** Telegram hands over one message, then nothing; the first $failSends sends answer 400. */
    private function telegramWithOneMessage(int $failSends, array $message = ['text' => 'toast']): void
    {
        $polls = 0;
        $sends = 0;
        $this->fakeHttpOnly(function (Request $r) use (&$polls, &$sends, $failSends, $message) {
            if (str_ends_with($r->url(), '/getUpdates')) {
                return Http::response(['ok' => true, 'result' => ++$polls === 1
                    ? [['update_id' => 7, 'message' => ['chat' => ['id' => 42]] + $message]] : []]);
            }

            return ++$sends <= $failSends ? Http::response(['ok' => false], 400) : Http::response(['ok' => true, 'result' => []]);
        });
    }

    private function sends(): int
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/sendMessage'))->count();
    }

    public function test_a_reply_that_fails_to_send_is_delivered_later_and_the_entry_saved_once(): void
    {
        Sleep::fake();
        $this->claudeSays(self::TOAST);
        $this->telegramWithOneMessage(failSends: 1);

        $this->assertSame(8, app(JournalBot::class)->poll(0), 'the offset moves past the message');
        $this->assertSame(1, ActivityLog::count());

        // A fresh instance, as after a restart: what is owed is in the cache, not in memory.
        app()->forgetInstance(JournalBot::class);
        app(JournalBot::class)->poll(8);
        $this->assertSame('(Delayed reply) Logged toast.', $this->sentText());
        $this->assertSame(1, ActivityLog::count(), 'nothing asked Rob to resend, so nothing doubles');

        app(JournalBot::class)->poll(8);
        $this->assertSame(2, $this->sends(), 'the owed reply is sent once, not every poll');
    }

    public function test_a_message_that_fails_before_saving_asks_for_a_resend(): void
    {
        Sleep::fake();
        $this->telegramWithOneMessage(failSends: 0, message: ['photo' => [['file_id' => 'F']]]);
        Http::fake(['*/getFile' => Http::response(['ok' => false], 400)]);
        $this->claudeSays(self::TOAST);

        $bot = app(JournalBot::class);
        $bot->poll(0);
        $bot->poll(8);

        $this->assertStringContainsString('before anything from it was saved', $this->sentText());
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_a_failure_half_way_through_saving_saves_nothing(): void
    {
        Sleep::fake();
        $this->telegramWithOneMessage(failSends: 0);
        $this->claudeSays(['reply' => 'Logged.', 'entries' => [
            self::TOAST['entries'][0],
            ['kind' => 'symptom', 'logged_at' => '2026-07-01T08:05:00+01:00', 'description' => 'Headache', 'severity' => 3],
        ]]);
        SymptomLog::creating(fn () => throw new \RuntimeException('disk full'));

        $bot = app(JournalBot::class);
        $bot->poll(0);
        $bot->poll(8);

        $this->assertSame(0, ActivityLog::count(), 'the toast saved before the failure is rolled back');
        $this->assertStringContainsString('before anything from it was saved', $this->sentText());
    }

    public function test_fifteen_minutes_offline_pushes_to_the_phone_once(): void
    {
        Sleep::fake();
        config(['services.ntfy.topic' => 'TOPIC']);
        $this->fakeHttpOnly([
            'api.telegram.org/*' => fn () => throw new ConnectionException('Resolving timed out'),
            'ntfy.sh/*' => Http::response('ok'),
        ]);
        $bot = app(JournalBot::class);
        $pushes = fn () => Http::recorded(fn (Request $r) => str_starts_with($r->url(), 'https://ntfy.sh/TOPIC'))->count();

        $bot->poll(0);
        $this->assertSame(0, $pushes(), 'a short blip is not worth a push');

        $this->travel(16)->minutes();
        $bot->poll(0);
        $bot->poll(0);
        $this->assertSame(1, $pushes());
    }

    public function test_the_about_file_reaches_claude_when_present(): void
    {
        $about = tempnam(sys_get_temp_dir(), 'about');
        file_put_contents($about, 'Fibre with every meal.');
        config(['services.telegram.about_file' => $about]);
        $this->claudeSays(['reply' => 'Hi.', 'entries' => []]);

        $this->message('hello');

        Process::assertRan(fn ($p) => str_contains($p->input, '## About Rob\\nFibre with every meal.')); // JSON-escaped newline
        unlink($about);
    }

    public function test_a_stranger_gets_silence_and_claude_is_never_called(): void
    {
        Process::fake();

        $this->message('hello', chat: 999);

        Process::assertNothingRan();
        Http::assertNothingSent();
    }

    public function test_a_correction_changes_the_row_and_a_removal_deletes_it(): void
    {
        $this->claudeSays(['reply' => 'Logged.', 'entries' => [
            ['kind' => 'food', 'logged_at' => 'now', 'description' => 'Pancakes, 3', 'calories' => 450],
            ['kind' => 'food', 'logged_at' => 'now', 'description' => 'Orange juice', 'calories' => 110],
        ]]);
        $this->message('pancakes and juice');
        [$pancakes, $juice] = ActivityLog::withoutGlobalScopes()->orderBy('id')->get();

        $this->claudeSays(['reply' => 'Fixed.', 'entries' => [
            ['kind' => 'food', 'logged_at' => 'now', 'description' => 'Pancakes, 4', 'calories' => 600, 'replaces_id' => $pancakes->id],
            ['kind' => 'food', 'logged_at' => 'now', 'description' => 'Orange juice', 'replaces_id' => $juice->id, 'remove' => true],
        ]]);
        $this->message('it was 4 pancakes, and no juice');

        $this->assertSame(['Pancakes, 4'], ActivityLog::withoutGlobalScopes()->pluck('notes')->all());
        $this->assertSame(600, $pancakes->fresh()->calories);
        $this->assertSame('Fixed.', $this->sentText());
    }

    public function test_a_correction_cannot_reach_another_users_row(): void
    {
        $food = ActivityType::withoutGlobalScopes()->where('slug', 'food')->value('id');
        $theirs = ActivityLog::withoutGlobalScopes()->create([
            'user_id' => User::factory()->create()->id, 'activity_type_id' => $food, 'logged_at' => now(), 'notes' => 'theirs',
        ]);
        $this->claudeSays(['reply' => 'Removed.', 'entries' => [
            ['kind' => 'food', 'logged_at' => 'now', 'description' => 'Theirs', 'replaces_id' => $theirs->id, 'remove' => true],
        ]]);

        $this->message('delete that');

        $this->assertNotNull(ActivityLog::withoutGlobalScopes()->find($theirs->id));
        $this->assertStringContainsString('Not saved: Theirs (entry to change not found)', $this->sentText());
    }

    public function test_another_users_medication_is_not_logged(): void
    {
        $theirs = Medication::withoutGlobalScopes()->create(['user_id' => User::factory()->create()->id, 'name' => 'X']);
        $this->claudeSays(['reply' => 'Logged your dose.', 'entries' => [[
            'kind' => 'medication', 'logged_at' => 'now', 'description' => 'Their pill', 'medication_id' => $theirs->id,
        ]]]);

        $this->message('took my pill');

        $this->assertSame(0, MedicationLog::withoutGlobalScopes()->count());
        $this->assertStringContainsString('Not saved: Their pill', $this->sentText());
    }

    public function test_a_medicine_not_on_the_list_is_added_once_then_reused(): void
    {
        $entry = ['kind' => 'medication', 'logged_at' => 'now', 'description' => 'Pill', 'medication_name' => 'Testamol', 'dosage_taken' => '1 tablet'];
        $this->claudeSays(['reply' => 'Logged.', 'entries' => [$entry]]);
        $this->message('took a testamol');
        $this->claudeSays(['reply' => 'Logged.', 'entries' => [['medication_name' => 'TESTAMOL'] + $entry]]);
        $this->message('took another');

        $this->assertSame(['Testamol'], Medication::withoutGlobalScopes()->get()->pluck('name')->all());
        $this->assertFalse(Medication::withoutGlobalScopes()->first()->is_active);
        $this->assertSame(2, MedicationLog::withoutGlobalScopes()->count());
    }

    public function test_a_claude_failure_saves_nothing_and_says_so(): void
    {
        Process::fake(['*' => Process::result('', 'boom', 1)]);

        $this->message('chips');

        $this->assertSame(0, ActivityLog::withoutGlobalScopes()->count());
        $this->assertSame('Sorry, I could not process that. Nothing was saved.', $this->sentText());
    }
}
