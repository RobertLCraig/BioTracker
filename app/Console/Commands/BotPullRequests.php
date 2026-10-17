<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Runs on the PC, where the board lives: collects what the Telegram bot could not log from the
 * server and raises one card per request in human-review/, for Rob to decide on.
 */
class BotPullRequests extends Command
{
    protected $signature = 'bot:pull-requests';

    protected $description = 'Turn the Telegram bot\'s feature requests into board cards';

    public function handle(): int
    {
        $server = config('services.telegram.server');
        $f = escapeshellarg(config('services.telegram.server_requests_file'));
        // Move the file aside first, so a request arriving mid-pull is kept for the next run; the
        // aside copy is deleted only after the cards exist, so a failed run loses nothing.
        $lines = Process::timeout(30)->run(['ssh', '-o', 'BatchMode=yes', $server,
            "[ -s $f ] && mv $f $f.taking; [ -s $f.taking ] && cat $f.taking; true",
        ])->throw()->output();

        $requests = collect(explode("\n", $lines))->map(fn ($l) => json_decode($l, true))
            ->filter(fn ($r) => is_array($r) && trim($r['title'] ?? '') !== '');
        if ($requests->isEmpty()) {
            $this->info('No new requests.');

            return self::SUCCESS;
        }

        $board = config('services.telegram.board_path');
        $next = collect(glob("$board/*/[0-9][0-9][0-9][0-9]-*.md"))
            ->map(fn ($p) => (int) substr(basename($p), 0, 4))->max() + 1;
        $written = [];
        foreach ($requests as $r) {
            $path = sprintf('%s/human-review/%04d-%s.md', $board, $next++, Str::slug(Str::limit($r['title'], 50, '')));
            file_put_contents($path, $this->card($r));
            $written[] = $path;
            $this->info('Raised '.basename($path));
        }

        Process::path(base_path())->run(['git', 'add', '--', ...$written])->throw();
        Process::path(base_path())->run(['git', 'commit', '-m', 'board: '.count($written).' feature request(s) from the Telegram bot', '--', ...$written])->throw();
        Process::timeout(30)->run(['ssh', '-o', 'BatchMode=yes', $server, "rm -f $f.taking"])->throw();

        return self::SUCCESS;
    }

    private function card(array $r): string
    {
        $date = $r['date'] ?? now()->toDateString();

        return <<<MD
            ---
            not_for_the_loop: raised by the Telegram bot; Rob decides whether it gets built
            ---
            # {$r['title']}

            ## Why
            {$r['why']}

            Raised by the Telegram bot on {$date}: Rob reported something it had no way to log.
            The wording is the bot's, kept general because this board is public.

            ## Acceptance
            <!-- AC:BEGIN -->
            - [ ] #1 WHEN Rob reports this to the Telegram bot, THE BOT SHALL log it in BioTracker.
                  (Rob: confirm, rewrite, or move this card to discarded/.)
            <!-- AC:END -->

            MD;
    }
}
