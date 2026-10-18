<?php

namespace App\Services\Bot;

use App\Enums\VitalType;
use App\Http\Requests\StoreActivityLogRequest;
use App\Http\Requests\StoreMedicationLogRequest;
use App\Http\Requests\StoreSymptomLogRequest;
use App\Http\Requests\StoreVitalLogRequest;
use App\Models\ActivityLog;
use App\Models\ActivityType;
use App\Models\Medication;
use App\Models\MedicationLog;
use App\Models\SymptomLog;
use App\Models\User;
use App\Models\UserStreak;
use App\Models\UserTask;
use App\Models\VitalLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Sleep;

/**
 * Telegram journal bot: one incoming message in, Claude turns it into log rows, one reply out.
 * Claude runs as the Claude Code CLI with every tool off, so it can only answer; this class
 * validates and writes whatever it proposes.
 */
class JournalBot
{
    private const HISTORY_TURNS = 12;

    private const ACTIVITY_KINDS = ['food', 'drink', 'exercise', 'sleep'];

    private const PROMPT = <<<'TXT'
        You are the journal assistant inside BioTracker, Rob's personal health journal. Rob texts
        you during the day about what he ate, drank, did or felt, sometimes with a photo.

        Turn each thing he reports into one entry. For food and drink, estimate the portion,
        calories and macros the way a careful dietitian would from the words or the photo. If a
        detail would change the numbers a lot and you cannot guess it sensibly, ask ONE short
        follow-up question and return no entries until he answers. Never log the same thing twice:
        the conversation so far and today's log are below.

        To correct an entry already logged, send it again in full with replaces_id set to its
        [#id] from today's log. To delete one, send its kind, replaces_id and remove: true.

        Kinds: food, drink, exercise, sleep, symptom (severity 1-10), medication, weight
        (quantity, unit "kg"). For medication, use its medication_id from the list below; for a
        medicine not on the list, leave medication_id out and set medication_name instead, and
        BioTracker adds it to his list (set regular: true only if he says he takes it regularly).
        logged_at is ISO 8601 with offset, in his timezone; use "now" unless he says when.

        If he reports something no kind fits, do not force it into another kind. Add a
        feature_request instead: a short title for what BioTracker should learn to log, and a why
        in general words with no personal health detail (it goes on a public board). Tell him you
        noted it as a request.

        reply: short and friendly, plain text for a phone. Say what you logged with the key
        numbers. When it is useful, comment on today's totals, his streak or his tasks.
        TXT;

    private const SCHEMA = [
        'type' => 'object',
        'required' => ['reply', 'entries'],
        'properties' => [
            'reply' => ['type' => 'string'],
            'entries' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'required' => ['kind', 'logged_at', 'description'],
                'properties' => [
                    'kind' => ['type' => 'string', 'enum' => ['food', 'drink', 'exercise', 'sleep', 'symptom', 'medication', 'weight']],
                    'logged_at' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'quantity' => ['type' => 'number'],
                    'unit' => ['type' => 'string'],
                    'calories' => ['type' => 'integer'],
                    'protein_g' => ['type' => 'number'],
                    'carbs_g' => ['type' => 'number'],
                    'fat_g' => ['type' => 'number'],
                    'duration_minutes' => ['type' => 'integer'],
                    'severity' => ['type' => 'integer'],
                    'body_area' => ['type' => 'string'],
                    'medication_id' => ['type' => 'integer'],
                    'medication_name' => ['type' => 'string'],
                    'regular' => ['type' => 'boolean'],
                    'dosage_taken' => ['type' => 'string'],
                    'replaces_id' => ['type' => 'integer'],
                    'remove' => ['type' => 'boolean'],
                ],
            ]],
            'feature_requests' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'required' => ['title', 'why'],
                'properties' => ['title' => ['type' => 'string'], 'why' => ['type' => 'string']],
            ]],
        ],
    ];

    public function handleUpdate(array $update): void
    {
        $msg = $update['message'] ?? null;
        if (! $msg) {
            return;
        }
        $chatId = (string) $msg['chat']['id'];

        // First-run helper: with no chat configured, tell the sender their id and do nothing else.
        if (! config('services.telegram.chat_id')) {
            $this->send($chatId, "Set TELEGRAM_CHAT_ID={$chatId} in .env, then restart the bot.");

            return;
        }
        if ($chatId !== (string) config('services.telegram.chat_id')) {
            return; // anyone else gets silence, not even a "not allowed"
        }

        Auth::setUser(User::findOrFail(config('services.telegram.user_id')));

        $text = trim($msg['text'] ?? $msg['caption'] ?? '');
        $photo = isset($msg['photo']) ? $this->downloadPhoto(end($msg['photo'])['file_id']) : null;
        if ($text === '/start') {
            $this->send($chatId, 'Hi Rob. Tell me what you eat, drink, do or feel, or send a photo of a meal.');

            return;
        }

        $historyKey = "telegram:{$chatId}:history";
        $history = Cache::get($historyKey, []);
        $said = trim(($photo ? '[photo] ' : '').$text);

        try {
            $answer = $this->ask($history, $said, $photo);
        } catch (\Throwable $e) {
            Log::error('JournalBot: Claude failed', ['error' => $e->getMessage()]);
            $this->send($chatId, 'Sorry, I could not process that. Nothing was saved.');

            return;
        }

        $reply = trim($answer['reply'] ?? '');
        // All or nothing, so an exception out of here means nothing was saved (poll relies on it).
        $failed = DB::transaction(fn () => $this->save($answer['entries'] ?? []));

        // Rows are saved from here on: nothing below may throw, or poll would say they were not.
        try {
            // One JSON line each; bot:pull-requests on the PC turns them into board cards.
            foreach ($answer['feature_requests'] ?? [] as $r) {
                file_put_contents(config('services.telegram.requests_file'), json_encode([
                    'title' => mb_substr((string) ($r['title'] ?? ''), 0, 120),
                    'why' => mb_substr((string) ($r['why'] ?? ''), 0, 1000),
                    'date' => now()->toDateString(),
                ])."\n", FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable $e) {
            Log::error('JournalBot: feature request not written', ['error' => $e->getMessage()]);
        }
        if ($failed) {
            $reply .= "\n\nNot saved: ".implode('; ', $failed);
        }

        try {
            $history[] = ['role' => 'Rob', 'text' => $said];
            $history[] = ['role' => 'You', 'text' => $reply];
            Cache::put($historyKey, array_slice($history, -self::HISTORY_TURNS), now()->addDay());
            $this->send($chatId, $reply);
        } catch (\Throwable $e) {
            Log::warning('JournalBot: reply not sent, kept for the next poll', ['error' => $e->getMessage()]);
            $this->owe("(Delayed reply) {$reply}");
        }
    }

    /** Owed messages live in the cache store (the database on the server), so a restart keeps them. */
    private function owe(string $text): void
    {
        Cache::put('telegram:owed', [...Cache::get('telegram:owed', []), $text], now()->addDays(2));
    }

    /** A message the bot starts itself; kept in the history so Rob's reply has context. */
    public function notify(string $text): void
    {
        $chatId = (string) config('services.telegram.chat_id');
        $historyKey = "telegram:{$chatId}:history";
        $history = Cache::get($historyKey, []);
        $history[] = ['role' => 'You', 'text' => $text];
        Cache::put($historyKey, array_slice($history, -self::HISTORY_TURNS), now()->addDay());

        $this->send($chatId, $text);
    }

    /** Seconds offline before the phone gets a push. */
    private const ALERT_AFTER = 900;

    private ?int $downSince = null;

    private bool $alerted = false;

    /**
     * One round of long polling; returns the next offset. A message whose handling throws is not
     * retried; handleUpdate only throws before its one save transaction commits, so nothing of it
     * was saved and Rob is asked to resend. A reply that could not be sent is kept and delivered
     * here once Telegram answers. A poll that keeps failing for ALERT_AFTER seconds pushes to the
     * phone, because Telegram is then the one channel that cannot carry the news.
     * ponytail: a process that crashes before it polls never alerts; systemd restarts it.
     */
    public function poll(int $offset): int
    {
        try {
            $updates = $this->telegram('getUpdates', ['offset' => $offset, 'timeout' => 50], 60)['result'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('telegram:poll getUpdates failed', ['error' => $e->getMessage()]);
            $this->downSince ??= now()->getTimestamp();
            if (! $this->alerted && now()->getTimestamp() - $this->downSince >= self::ALERT_AFTER) {
                $this->alerted = $this->push('ubuntu-web: 1 service down',
                    'Down 15+ min. ssh rob@192.168.222.134 journalctl -u telegram-bot -n 50');
            }
            Sleep::for(5)->seconds();

            return $offset;
        }
        $this->downSince = null;
        $this->alerted = false;

        $owed = Cache::get('telegram:owed', []);
        while ($owed) {
            try {
                $this->send((string) config('services.telegram.chat_id'), $owed[0]);
            } catch (\Throwable $e) {
                Log::warning('telegram:poll could not deliver an owed message', ['error' => $e->getMessage()]);
                break;
            }
            array_shift($owed);
            Cache::put('telegram:owed', $owed, now()->addDays(2));
        }

        foreach ($updates as $update) {
            $offset = $update['update_id'] + 1; // advance first: a message that crashes is not retried forever
            try {
                $this->handleUpdate($update);
            } catch (\Throwable $e) {
                Log::error('telegram:poll update failed', ['error' => $e->getMessage()]);
                $this->owe('Sorry, one of your messages failed on my side before anything from it was saved. Please send it again.');
            }
        }

        return $offset;
    }

    /** Best effort: a push that fails must not stop the bot. The topic is a secret, never logged. */
    private function push(string $title, string $body): bool
    {
        $topic = (string) config('services.ntfy.topic');
        if ($topic === '') {
            return false;
        }
        try {
            Http::timeout(20)->withHeaders(['Title' => $title, 'Priority' => '4', 'Tags' => 'warning'])
                ->withBody($body, 'text/plain')->post('https://ntfy.sh/'.$topic)->throw();

            return true;
        } catch (\Throwable) {
            Log::warning('telegram:poll alert push failed');

            return false;
        }
    }

    /** @return array{reply: string, entries: array} */
    private function ask(array $history, string $said, ?string $photoBase64): array
    {
        $transcript = collect($history)->map(fn ($t) => "{$t['role']}: {$t['text']}")->implode("\n");
        $content = [['type' => 'text', 'text' => $this->context()."\n\n## Conversation so far\n"
            .($transcript ?: '(none)')."\n\n## Rob now says\n".($said ?: '(photo only)')]];
        if ($photoBase64) {
            array_unshift($content, ['type' => 'image', 'source' => [
                'type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $photoBase64,
            ]]);
        }
        $input = json_encode(['type' => 'user', 'message' => ['role' => 'user', 'content' => $content]]);

        $result = Process::timeout(180)->input($input."\n")->run([
            config('services.claude.bin'), '-p',
            '--model', config('services.claude.model'),
            '--tools', '', // no tools: it can only answer
            '--input-format', 'stream-json', '--output-format', 'stream-json', '--verbose',
            '--json-schema', json_encode(self::SCHEMA),
            '--system-prompt', preg_replace('/\s+/', ' ', self::PROMPT), // one line: safe as a Windows arg
            '--no-session-persistence', '--strict-mcp-config', '--setting-sources', '',
        ]);

        $final = collect(explode("\n", $result->output()))
            ->map(fn ($line) => json_decode($line, true))
            ->first(fn ($e) => ($e['type'] ?? null) === 'result');
        if (! $result->successful() || ! $final || ($final['is_error'] ?? true) || ! isset($final['structured_output'])) {
            throw new \RuntimeException('claude exit '.$result->exitCode().': '.substr($result->errorOutput() ?: $result->output(), -500));
        }

        return $final['structured_output'];
    }

    private function context(): string
    {
        $tz = config('services.telegram.timezone');
        $now = Carbon::now($tz);
        $from = $now->copy()->startOfDay()->setTimezone(config('app.timezone'));
        $local = fn ($t) => $t->copy()->setTimezone($tz)->format('H:i');

        // forUser(): the activityType relation's global scope hides the shared system types.
        $slugs = ActivityType::forUser()->pluck('slug', 'id');
        $lines = ["## Now\n".$now->toIso8601String()." ({$tz})", '## Logged today'];
        // Personal context lives only on the server, never in the (public) repo.
        $about = config('services.telegram.about_file');
        if ($about && is_readable($about)) {
            array_unshift($lines, "## About Rob\n".trim(file_get_contents($about)));
        }
        foreach (ActivityLog::where('logged_at', '>=', $from)->orderBy('logged_at')->get() as $l) {
            $m = $l->metadata ?? [];
            $lines[] = "- [#{$l->id}] {$local($l->logged_at)} {$slugs[$l->activity_type_id]}: {$l->notes}"
                .($l->calories !== null ? ", {$l->calories} kcal" : '')
                .(isset($m['protein_g']) ? ", P{$m['protein_g']} C".($m['carbs_g'] ?? '?').' F'.($m['fat_g'] ?? '?') : '');
        }
        foreach (SymptomLog::where('logged_at', '>=', $from)->get() as $s) {
            $lines[] = "- [#{$s->id}] {$local($s->logged_at)} symptom: {$s->symptom} ({$s->severity}/10)";
        }
        foreach (MedicationLog::where('taken_at', '>=', $from)->get() as $d) {
            $lines[] = "- [#{$d->id}] {$local($d->taken_at)} medication {$d->medication_id}: {$d->dosage_taken}";
        }

        $lines[] = '## Recent weigh-ins (newest first)';
        foreach (VitalLog::where('type', VitalType::Weight->value)->latest('logged_at')->take(5)->get() as $w) {
            $lines[] = "- [#{$w->id}] {$w->logged_at->copy()->setTimezone($tz)->toDateString()}: {$w->value} {$w->unit}";
        }

        $lines[] = '## Medications (id: name dosage)';
        foreach (Medication::all() as $med) {
            $lines[] = "- {$med->id}: {$med->name} {$med->dosage}{$med->unit}".($med->is_active ? '' : ' (not regular)');
        }

        $streak = UserStreak::first();
        $lines[] = '## Streak';
        $lines[] = $streak ? "{$streak->current_streak} days (best {$streak->longest_streak})" : 'none yet';

        $lines[] = '## Active tasks';
        foreach (UserTask::where('is_active', true)->withCount(['completions' => fn ($q) => $q->where('completed_at', '>=', $from)])->get() as $task) {
            $lines[] = "- {$task->title} ({$task->frequency?->value})".($task->completions_count ? ' - done today' : '');
        }

        return implode("\n", $lines);
    }

    /**
     * Writes each proposed entry through the same rules as the API, as the bot's user.
     *
     * @return string[] one line per entry that was not saved
     */
    private function save(array $entries): array
    {
        $failed = [];
        foreach ($entries as $e) {
            // A correction or removal names an existing row; the user scope keeps it to Rob's own.
            $table = in_array($e['kind'] ?? null, self::ACTIVITY_KINDS, true) ? ActivityLog::class
                : ['symptom' => SymptomLog::class, 'medication' => MedicationLog::class, 'weight' => VitalLog::class][$e['kind'] ?? ''] ?? null;
            $existing = isset($e['replaces_id']) && $table ? $table::find($e['replaces_id']) : null;
            if (isset($e['replaces_id']) && ! $existing) {
                $failed[] = ($e['description'] ?? '?').' (entry to change not found)';

                continue;
            }
            if ($existing && ! empty($e['remove'])) {
                $existing->delete();

                continue;
            }

            try {
                $at = Carbon::parse($e['logged_at'] ?? 'now', config('services.telegram.timezone'))
                    ->setTimezone(config('app.timezone'));
            } catch (\Throwable) {
                $failed[] = ($e['description'] ?? '?').' (bad time)';

                continue;
            }
            [$model, $data, $rules] = $this->toRow($e, $at);

            if ($model === null || Validator::make($data, Arr::except($rules, ['photos', 'photos.*']))->fails()) {
                $failed[] = $e['description'] ?? '?';

                continue;
            }
            $existing ? $existing->update($data) : $model::create($data);
        }

        return $failed;
    }

    /** @return array{0: class-string|null, 1: array, 2: array} */
    private function toRow(array $e, Carbon $at): array
    {
        $kind = $e['kind'] ?? null;

        if (in_array($kind, self::ACTIVITY_KINDS, true)) {
            $type = ActivityType::forUser()->where('slug', $kind)->first();

            return [$type ? ActivityLog::class : null, [
                'activity_type_id' => $type?->id,
                'logged_at' => $at,
                'notes' => $e['description'] ?? null,
                'quantity' => $e['quantity'] ?? null,
                'unit' => $e['unit'] ?? null,
                'calories' => $e['calories'] ?? null,
                'duration_minutes' => $e['duration_minutes'] ?? null,
                'metadata' => array_filter([
                    'source' => 'telegram',
                    'protein_g' => $e['protein_g'] ?? null,
                    'carbs_g' => $e['carbs_g'] ?? null,
                    'fat_g' => $e['fat_g'] ?? null,
                ], fn ($v) => $v !== null),
            ], (new StoreActivityLogRequest)->rules()];
        }

        if ($kind === 'weight') {
            return [VitalLog::class, [
                'type' => VitalType::Weight->value,
                'value' => $e['quantity'] ?? null,
                'unit' => $e['unit'] ?? 'kg',
                'logged_at' => $at,
                'source' => 'manual',
                'notes' => $e['description'] ?? null,
            ], (new StoreVitalLogRequest)->rules()];
        }

        if ($kind === 'symptom') {
            return [SymptomLog::class, [
                'symptom' => $e['description'] ?? null,
                'severity' => $e['severity'] ?? null,
                'body_area' => $e['body_area'] ?? null,
                'logged_at' => $at,
                'duration_minutes' => $e['duration_minutes'] ?? null,
            ], (new StoreSymptomLogRequest)->rules()];
        }

        $med = $kind === 'medication' ? $this->medication($e) : null;
        if ($med) {
            return [MedicationLog::class, [
                'medication_id' => $med->id,
                'taken_at' => $at,
                'dosage_taken' => $e['dosage_taken'] ?? null,
                'notes' => $e['description'] ?? null,
            ], (new StoreMedicationLogRequest)->rules()];
        }

        return [null, [], []];
    }

    /**
     * The medication an entry names: by id, else by name, else a new one on Rob's list. The
     * rule's exists: check is not per user; the scoped find() is. Names are encrypted at rest,
     * so the name match runs in PHP over his own short list.
     */
    private function medication(array $e): ?Medication
    {
        if (isset($e['medication_id'])) {
            return Medication::find($e['medication_id']);
        }
        $name = trim($e['medication_name'] ?? '');
        if ($name === '' || mb_strlen($name) > 255) {
            return null;
        }

        return Medication::all()->first(fn ($m) => mb_strtolower($m->name) === mb_strtolower($name))
            ?? Medication::create(['name' => $name, 'dosage' => $e['dosage_taken'] ?? null, 'is_active' => (bool) ($e['regular'] ?? false)]);
    }

    private function downloadPhoto(string $fileId): string
    {
        $path = $this->telegram('getFile', ['file_id' => $fileId])['result']['file_path'];

        return base64_encode($this->call(30, fn ($http, $token) => $http->get("https://api.telegram.org/file/bot{$token}/{$path}"))->body());
    }

    public function send(string $chatId, string $text): void
    {
        $this->telegram('sendMessage', ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4000)]);
    }

    public function telegram(string $method, array $params = [], int $timeout = 30): array
    {
        return $this->call($timeout, fn ($http, $token) => $http->post("https://api.telegram.org/bot{$token}/{$method}", $params))->json();
    }

    /**
     * Every Telegram request: retried when the connection fails (the home DNS drops lookups now
     * and then), and its error re-thrown with the token cut out, because the token is in the URL
     * and errors end up in the log.
     */
    private function call(int $timeout, callable $request): Response
    {
        $token = (string) config('services.telegram.token');
        try {
            $http = Http::timeout($timeout)->retry(3, 2000, fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())));

            return $request($http, $token)->throw();
        } catch (\Throwable $e) {
            throw new \RuntimeException($token === '' ? $e->getMessage() : str_replace($token, '[token]', $e->getMessage()));
        }
    }
}
