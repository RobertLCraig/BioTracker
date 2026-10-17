<?php

namespace App\Console\Commands;

use App\Services\Bot\JournalBot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Evening pollen check: when tomorrow's forecast is high, the bot suggests a hayfever tablet.
 * Open-Meteo's air-quality API (CAMS, Europe) is free and needs no key.
 */
class TelegramPollen extends Command
{
    protected $signature = 'telegram:pollen';

    protected $description = 'Message Rob if pollen will be high tomorrow';

    // ponytail: rough "high" levels in grains/m3 after the Met Office grass bands; tune if alerts feel wrong.
    private const HIGH = ['grass' => 50, 'birch' => 80, 'alder' => 80, 'mugwort' => 30, 'olive' => 50, 'ragweed' => 20];

    public function handle(JournalBot $bot): int
    {
        $tz = config('services.telegram.timezone');
        $hourly = Http::timeout(30)->get('https://air-quality-api.open-meteo.com/v1/air-quality', [
            'latitude' => config('services.telegram.latitude'),
            'longitude' => config('services.telegram.longitude'),
            'hourly' => collect(self::HIGH)->keys()->map(fn ($p) => "{$p}_pollen")->implode(','),
            'forecast_days' => 2,
            'timezone' => $tz,
        ])->throw()->json('hourly');

        $tomorrow = Carbon::tomorrow($tz)->toDateString();
        $high = [];
        foreach (self::HIGH as $pollen => $level) {
            $peak = collect($hourly["{$pollen}_pollen"] ?? [])
                ->filter(fn ($v, $i) => str_starts_with($hourly['time'][$i], $tomorrow))
                ->max();
            if ($peak !== null && $peak >= $level) {
                $high[] = "{$pollen} ".round($peak);
            }
        }

        if (! $high) {
            $this->info('Pollen not high tomorrow.');

            return self::SUCCESS;
        }
        $bot->notify('Pollen will be high tomorrow ('.implode(', ', $high).' grains/m³). '
            .'Worth taking a hayfever tablet in the morning.');
        $this->info('Sent: '.implode(', ', $high));

        return self::SUCCESS;
    }
}
