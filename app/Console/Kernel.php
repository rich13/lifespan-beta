<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        
        // Warm Desert Island Discs cache daily at 2 AM
        $schedule->command('cache:warm-desert-island-discs')
            ->daily()
            ->at('02:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Public span page cache: no scheduled warming; pages cache on first visit and stay until invalidation or deploy (cache:clear).
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');

        $this->commands = [
            Commands\ImportYaml::class,
            Commands\DatabaseCreateCommand::class,
            Commands\WarmDesertIslandDiscsCache::class,
            Commands\WarmPublicSpanPageCache::class,
            Commands\AnalysePlaqueResidencePatterns::class,
            Commands\ListPlaquePersonPlaceCandidates::class,
        ];
    }
}
