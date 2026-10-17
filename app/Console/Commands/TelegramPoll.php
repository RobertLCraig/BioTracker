<?php

namespace App\Console\Commands;

use App\Services\Bot\JournalBot;
use Illuminate\Console\Command;

/**
 * Long-polls Telegram for the journal bot. Polling needs no public URL, so this runs anywhere
 * with outbound internet; keep it alive with systemd or Supervisor.
 */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Run the Telegram journal bot (long polling)';

    public function handle(JournalBot $bot): int
    {
        if (! config('services.telegram.token')) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }
        $this->info('Journal bot polling. Ctrl+C to stop.');
        $offset = 0;

        while (true) {
            $offset = $bot->poll($offset);
        }
    }
}
